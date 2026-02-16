<?php

namespace Nexia\QueueOrchestrator;

use Illuminate\Support\ServiceProvider;
use Nexia\QueueOrchestrator\Commands\LaunchQueueCommand;
use Nexia\QueueOrchestrator\Services\QueueRegistry;

class QueueOrchestratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register the Registry as a Singleton so definitions persist
        $this->app->singleton(QueueRegistry::class, function ($app) {
            return new QueueRegistry();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                LaunchQueueCommand::class,
            ]);
        }
    }
}