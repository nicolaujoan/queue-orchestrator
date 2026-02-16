<?php

namespace Nexia\QueueOrchestrator\Services;

use Illuminate\Contracts\Queue\ShouldQueue;
use RuntimeException;

class QueueRegistry
{
    /**
     * Map of queue names to their Job creation logic.
     * @var array<string, callable>
     */
    protected array $registry = [];

    /**
     * Register a queue and a closure to build the job.
     *
     * @param string $queueName The name of the queue (e.g., 'riu-novelties')
     * @param callable $jobFactory A closure that receives ?string $args and returns a Job instance.
     */
    public function register(string $queueName, callable $jobFactory): void
    {
        $this->registry[$queueName] = $jobFactory;
    }

    /**
     * Check if a queue is registered.
     */
    public function has(string $queueName): bool
    {
        return array_key_exists($queueName, $this->registry);
    }

    /**
     * Get all registered queue names.
     */
    public function getAvailableQueues(): array
    {
        return array_keys($this->registry);
    }

    /**
     * Create a job instance using the registered factory.
     */
    public function createJob(string $queueName, ?string $args = null): ShouldQueue
    {
        if (!$this->has($queueName)) {
            throw new RuntimeException("Queue [{$queueName}] is not registered via QueueRegistry.");
        }

        $factory = $this->registry[$queueName];
        
        // Execute the closure provided by the host app to get the Job
        $job = $factory($args);

        if (!$job instanceof ShouldQueue) {
            throw new RuntimeException("The factory for [{$queueName}] must return a ShouldQueue object.");
        }

        return $job;
    }
}