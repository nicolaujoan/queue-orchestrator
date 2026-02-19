<?php

namespace Nexia\QueueOrchestrator\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Scheduling\ScheduleRunCommand;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;

class ScheduleRunSilent extends ScheduleRunCommand
{
    /**
     * The console command name.
     * By using 'schedule:run', we override the default Laravel command.
     *
     * @var string
     */
    protected $name = 'schedule:run';

    /**
     * Execute the console command.
     */
    public function handle(
        Schedule $schedule, 
        Dispatcher $dispatcher, 
        CacheRepository $cache, 
        ExceptionHandler $handler
    ) {
        $events = $schedule->dueEvents($this->laravel);

        // If no events are due, exit silently without printing the INFO block
        if (count($events) === 0) {
            return 0;
        }

        return parent::handle($schedule, $dispatcher, $cache, $handler);
    }
}