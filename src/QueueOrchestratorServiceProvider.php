<?php

namespace Nexia\QueueOrchestrator;

use Illuminate\Support\ServiceProvider;
use Org\QueueOrchestrator\Commands\LaunchQueueCommand;
use Org\QueueOrchestrator\Services\QueueRegistry;

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