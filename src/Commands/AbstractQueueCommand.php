<?php

namespace Nexia\QueueOrchestrator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

abstract class AbstractQueueCommand extends Command
{
    abstract protected function getQueueName(): string;
    abstract protected function getDefaultWorkers(): int;

    protected function getChildQueueName(): ?string { return null; }
    protected function getChildWorkers(): int { return $this->getWorkerCount(); }
    protected function needsMainQueueWorkers(): bool { return true; }
    protected function needsChildQueueWorkers(): bool { return true; }
    protected function getMaxWorkers(): int { return 10; }
    protected function needsChaining(): bool { return true; }
    
    // Hooks for next actions
    protected function getNextCommand(): ?string { return null; }
    protected function shouldQueueNextCommand(): bool { return true; }
    
    /**
     * @return array<object>
     */
    protected function getNextJobs(): array { return []; }

    public function handle(): int
    {
        $startTime = microtime(true);
        $queueName = $this->getQueueName();
        $workers = $this->getWorkerCount();

        // 0. Check Queue Size
        $existingJobs = $this->getQueueSize($queueName);
        if ($existingJobs === 0) {
            // Even if empty, we might need to process child queues from previous runs?
            // Original logic returns 0 immediately. Keeping original logic.
            return 0;
        }

        $this->info("=== Queue Worker: [{$queueName}] ===");
        $this->info("Jobs: {$existingJobs} | Workers: {$workers}");

        // 1. Process Main Queue
        $mainStartTime = microtime(true);
        $mainWorkers = $this->needsMainQueueWorkers() ? $workers : 1;
        $this->spawnWorkers($queueName, $mainWorkers);
        $mainTime = round(microtime(true) - $mainStartTime, 2);

        // 2. Process Child Queue
        $childTime = 0;
        $childQueueName = $this->getChildQueueName();
        if ($childQueueName && $this->needsChaining()) {
            $childJobs = $this->getQueueSize($childQueueName);
            if ($childJobs > 0) {
                $childWorkers = $this->needsChildQueueWorkers() ? $this->getChildWorkers() : 1;
                $this->info("Processing child queue [{$childQueueName}] ({$childJobs} jobs)...");
                $childStartTime = microtime(true);
                $this->spawnWorkers($childQueueName, $childWorkers);
                $childTime = round(microtime(true) - $childStartTime, 2);
            }
        }

        // 3. Dispatch Next Jobs
        $nextJobs = $this->getNextJobs();
        if (!empty($nextJobs)) {
            $this->newLine();
            $this->info("=== Dispatching " . count($nextJobs) . " next job(s) ===");
            foreach ($nextJobs as $job) {
                dispatch($job);
                $this->info("  Dispatched: " . class_basename($job));
            }
        }

        // 4. Execute Next Command
        $nextCommand = $this->getNextCommand();
        if ($nextCommand) {
            $this->newLine();
            if ($this->shouldQueueNextCommand()) {
                Artisan::queue($nextCommand);
                $this->info("  Queued next command: {$nextCommand}");
            } else {
                $this->info("  Executing next command: {$nextCommand}");
                $this->call($nextCommand);
            }
        }

        return 0;
    }

    protected function getWorkerCount(): int
    {
        $workers = (int) $this->option('workers');
        return max(1, min($workers, $this->getMaxWorkers()));
    }

    protected function getQueueConnection(): string
    {
        return config('queue.default', 'redis');
    }

    protected function getQueueSize(string $queueName): int
    {
        $connection = $this->getQueueConnection();
        return app('queue')->connection($connection)->size($queueName);
    }

    protected function spawnWorkers(string $queueName, int $count): void
    {
        $processes = [];
        // Use base_path to ensure we hit the project's artisan binary
        $artisanPath = base_path('artisan'); 
        $phpBinary = PHP_BINARY;
        $connection = $this->getQueueConnection();
        
        for ($i = 0; $i < $count; $i++) {
            // Construct command: php artisan queue:work ...
            $command = "{$phpBinary} {$artisanPath} queue:work {$connection} --queue={$queueName} --stop-when-empty";

            $descriptors = [
                0 => ['pipe', 'r'], // stdin
                1 => ['pipe', 'w'], // stdout
                2 => ['pipe', 'w'], // stderr
            ];

            $process = proc_open($command, $descriptors, $pipes);

            if (is_resource($process)) {
                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);
                $processes[] = ['process' => $process, 'pipes' => $pipes, 'index' => $i + 1];
                $this->info("  Worker " . ($i + 1) . " started.");
            }
        }

        // Monitor processes (simplified for brevity, keeping your original polling logic is fine)
        // Note: For production package, ensure you include the polling loop from your original file here.
        // I am including the specific polling loop logic below:
        
        $running = count($processes);
        while ($running > 0) {
            foreach ($processes as $key => &$p) {
                if (!isset($p['process'])) continue;
                
                $status = proc_get_status($p['process']);
                
                // Read output buffers
                $out = stream_get_contents($p['pipes'][1]);
                $err = stream_get_contents($p['pipes'][2]);
                if ($out) $this->line(trim($out));
                if ($err) $this->error(trim($err));

                if (!$status['running']) {
                    fclose($p['pipes'][0]);
                    fclose($p['pipes'][1]);
                    fclose($p['pipes'][2]);
                    proc_close($p['process']);
                    unset($processes[$key]);
                    $running--;
                    $this->info("  Worker {$p['index']} completed.");
                }
            }
            if ($running > 0) usleep(100000); // 100ms
        }
    }
}