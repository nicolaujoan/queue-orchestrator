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

Add the repository to your project's `composer.json`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/nicolaujoan/queue-orchestrator.git"
    }
],
"require": {
    "nicolaujoan/queue-orchestrator": "^1.0"
}

```

### 2. Register Service Provider

For Laravel 11 and 12, add the package provider to your `bootstrap/providers.php`:

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
    Org\QueueOrchestrator\QueueOrchestratorServiceProvider::class, // Add this line
];

```

---

## 🛠️ Implementation Guide

### 1. Register Your Jobs

Populate the `QueueRegistry` within your `AppServiceProvider.php` to map keys to Job instances.

```php
use Org\QueueOrchestrator\Services\QueueRegistry;
use App\Jobs\ProcessOrders;

public function boot(QueueRegistry $registry)
{
    // Simple registration
    $registry->register('orders', fn() => new ProcessOrders());

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

use Org\QueueOrchestrator\Commands\AbstractQueueCommand;

class ExecuteOrders extends AbstractQueueCommand
{
    protected $signature = 'execute:orders {--workers=5}';

    protected function getQueueName(): string { return 'orders'; }
    protected function getDefaultWorkers(): int { return 5; }

    protected function getNextJobs(): array
    {
        // Automatically dispatch cleanup after orders are processed
        return [new \App\Jobs\CleanupProcessedOrders()];
    }
}

```

### 3. Schedule via Parser

Decouple your schedule logic from the code by using config-driven strings.

```php
use Org\QueueOrchestrator\Services\ScheduleParser;

$event = Schedule::job(new \App\Jobs\ProcessOrders());

ScheduleParser::apply($event, config('tasks.order_sync'))
    ->withoutOverlapping();

```

---

## 🚀 Usage

### Manual Dispatching

Trigger any registered job via CLI. Arguments are passed after `::`.

```bash
# Basic usage
php artisan orchestrator:launch orders

# With parameters
php artisan orchestrator:launch reports::financial

```

### Running the Orchestrator

Execute your custom command to start the worker management process.

```bash
php artisan execute:orders --workers=10

```

---

## 📖 Parameterized Jobs Use Cases

The `::args` suffix allows a single job class to adapt based on CLI input.

### 💡 Option A: Simple Toggle (Ternary)

*Best for booleans or single default values.*

```php
$registry->register('marketing-sync', function(?string $args) {
    // Command: php artisan orchestrator:launch marketing-sync::force
    $forceUpdate = ($args === 'force');
    
    return new MarketingSyncJob(force: $forceUpdate);
});

```

### 🏆 Option B: Complex Mapping (Match)

*Best for multiple aliases and type checking.*

```php
$registry->register('data-export', function(?string $args) {
    // Command: php artisan orchestrator:launch data-export::csv
    $format = match(true) {
        $args === 'csv'   => 'text/csv',
        $args === 'pdf'   => 'application/pdf',
        is_null($args)    => 'application/json',
        default           => throw new \Exception("Unsupported format"), 
    };
    
    return new DataExportJob(mimeType: $format);
});

```

---

## 📋 Cheat Sheet

### Schedule Formats

| Format | Example | Description |
| --- | --- | --- |
| **Cron** | `* * * * *` | Standard cron expression |
| **Predefined** | `hourly` | Laravel's built-in hourly |
| **Custom** | `monthly:1,02:00` | Specific day and time |

> [!TIP]
> **Workflow for new processes:**
> 1. Create **Job** → 2. Register in **Registry** → 3. Create **Command** → 4. Set **Schedule**.
> 
> 