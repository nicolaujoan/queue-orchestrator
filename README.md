# 🚀 Queue Orchestrator

Standardize complex queue chains, worker scaling, and dynamic scheduling across your organization's projects.

---

## ✨ Features

* **Standardized Orchestration**: Inherit `AbstractQueueCommand` to manage parallel workers automatically.
* **Unified Launcher**: Dispatch any registered job via `php artisan orchestrator:launch`.
* **Dynamic Scheduling**: Controlled via string-based configs from your `.env` or Database.
* **Auto-Scaling**: Dynamically spawns `queue:work` processes based on real-time queue size.

---

## 📦 Installation

### 1. Composer Setup

Add the package via composer (once published to Packagist):

```bash
composer require nexia/queue-orchestrator

```

*Note: If using a private repository before publishing, add the VCS link to your `composer.json` first.*

### 2. Register Service Provider

For Laravel 11 and 12, add the package provider to your `bootstrap/providers.php`:

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    Nexia\QueueOrchestrator\QueueOrchestratorServiceProvider::class, // Add this line
];

```

---

## 🛠️ Implementation Guide

### 1. Register Your Jobs

Populate the `QueueRegistry` within your `AppServiceProvider.php` to map keys to Job instances.

```php
use Nexia\QueueOrchestrator\Services\QueueRegistry;
use App\Jobs\ProcessData;

public function boot(QueueRegistry $registry)
{
    // Simple registration
    $registry->register('data-sync', fn() => new ProcessData());

    // Registration with arguments (supports 'queue-name::arg-value')
    $registry->register('reports', function(?string $arg) {
        return new GenerateReport(type: $arg ?? 'standard');
    });
}

```

### 2. Create the Orchestrator Command

Extend `AbstractQueueCommand` to create a manager for your workers.

```php
namespace App\Console\Commands;

use Nexia\QueueOrchestrator\Commands\AbstractQueueCommand;

class ExecuteDataSync extends AbstractQueueCommand
{
    protected $signature = 'execute:data-sync {--workers=5}';

    protected function getQueueName(): string { return 'data-sync'; }
    protected function getDefaultWorkers(): int { return 5; }

    protected function getNextJobs(): array
    {
        // Automatically dispatch cleanup or downstream jobs after the queue is empty
        return [new \App\Jobs\NotifySyncCompletion()];
    }
}

```

### 3. Schedule via Parser

Decouple your schedule logic from the code by using config-driven strings.

```php
use Nexia\QueueOrchestrator\Services\ScheduleParser;

$event = Schedule::job(new \App\Jobs\ProcessData());

ScheduleParser::apply($event, config('tasks.sync_schedule'))
    ->withoutOverlapping();

```

---

## 🚀 Usage

### Manual Dispatching

Trigger any registered job via CLI. Arguments are passed after `::`.

```bash
# Basic usage
php artisan orchestrator:launch data-sync

# With parameters
php artisan orchestrator:launch reports::financial

```

### Running the Orchestrator

Execute your custom command to start the worker management process. This is what you usually put in your system scheduler.

```bash
php artisan execute:data-sync --workers=10

```

---

## 📖 Parameterized Jobs Use Cases

The `::args` suffix allows a single job class to adapt based on CLI input.

### 💡 Use Case 1: Simple Toggle (Ternary)

*Best for booleans or single default values.*

```php
$registry->register('email-blast', function(?string $args) {
    // Command: php artisan orchestrator:launch email-blast::test-mode
    $isTest = ($args === 'test-mode');
    
    return new SendEmailBlast(isTest: $isTest);
});

```

### 🏆 Use Case 2: Complex Mapping (Match)

*Best for multiple aliases and type checking.*

```php
$registry->register('export-logs', function(?string $args) {
    // Command: php artisan orchestrator:launch export-logs::csv
    $format = match(true) {
        $args === 'csv'   => 'text/csv',
        $args === 'pdf'   => 'application/pdf',
        is_numeric($args) => 'id_specific_export',
        default           => 'application/json', 
    };
    
    return new ExportLogsJob(mode: $format);
});

```

---

## 📋 Cheat Sheet

### Schedule Formats

| Format | Example | Description |
| --- | --- | --- |
| **Cron** | `* * * * *` | Standard cron expression |
| **Predefined** | `hourly` | Laravel's built-in hourly |
| **Custom** | `monthly:1,02:00` | Specific day (1st) and time (2 AM) |

> [!TIP]
> **Workflow for new processes:**
> 1. Create **Job** → 2. Register in **Registry** → 3. Create **Command** → 4. Set **Schedule**.
> 
> 

---

## ⚖️ License

This project is licensed under the MIT License - see the [LICENSE](https://www.google.com/search?q=LICENSE) file for details.