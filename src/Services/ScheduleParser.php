<?php

namespace Nexia\QueueOrchestrator\Services;

use Illuminate\Console\Scheduling\Event;
use InvalidArgumentException;

class ScheduleParser
{
    public static function apply(Event $event, string $scheduleConfig): Event
    {
        match (true) {
            str_starts_with($scheduleConfig, 'monthly:') => self::applyMonthly($event, $scheduleConfig),
            $scheduleConfig === 'hourly' => $event->hourly(),
            in_array($scheduleConfig, ['everyThirtyMinutes', '*/30']) => $event->everyThirtyMinutes(),
            in_array($scheduleConfig, ['everyFifteenMinutes', '*/15']) => $event->everyFifteenMinutes(),
            in_array($scheduleConfig, ['everyTenMinutes', '*/10']) => $event->everyTenMinutes(),
            in_array($scheduleConfig, ['everyFiveMinutes', '*/5']) => $event->everyFiveMinutes(),
            str_contains($scheduleConfig, ' ') => $event->cron($scheduleConfig),
            default => throw new InvalidArgumentException("Unsupported schedule format: {$scheduleConfig}")
        };

        return $event;
    }

    private static function applyMonthly(Event $event, string $scheduleConfig): Event
    {
        $config = substr($scheduleConfig, 8); 
        [$day, $hour] = explode(',', $config);

        return $event->monthlyOn((int) $day, trim($hour));
    }
}