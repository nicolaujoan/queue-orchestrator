<?php

namespace Nexia\QueueOrchestrator\Commands;

use Illuminate\Console\Command;
use Org\QueueOrchestrator\Services\QueueRegistry;

class LaunchQueueCommand extends Command
{
    protected $signature = 'orchestrator:launch {queue}';

    protected $description = 'Dispatch a job by queue name (supports ::args syntax)';

    public function handle(QueueRegistry $registry): int
    {
        $input = $this->argument('queue');

        // Parse queue name and optional args (e.g., 'riu-novelties::specific-users')
        $baseQueue = str_contains($input, '::') ? explode('::', $input, 2)[0] : $input;
        $args = str_contains($input, '::') ? explode('::', $input, 2)[1] : null;

        if (!$registry->has($baseQueue)) {
            $this->error("Unknown queue: {$baseQueue}");
            $this->info('Available queues: ' . implode(', ', $registry->getAvailableQueues()));
            return 1;
        }

        try {
            $job = $registry->createJob($baseQueue, $args);
            dispatch($job);
            
            $jobClass = class_basename(get_class($job));
            $this->info("{$jobClass} dispatched to queue [{$baseQueue}] successfully.");
            
            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}