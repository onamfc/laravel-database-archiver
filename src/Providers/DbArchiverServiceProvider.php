<?php

namespace onamfc\LaravelDbArchiver;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use onamfc\LaravelDbArchiver\Commands\ArchiveAllCommand;
use onamfc\LaravelDbArchiver\Commands\ArchiveStatusCommand;
use onamfc\LaravelDbArchiver\Commands\ArchiveTableCommand;
use onamfc\LaravelDbArchiver\Services\ArchiveService;
use onamfc\LaravelDbArchiver\Services\StorageManager;

class DbArchiverServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/db-archiver.php', 'db-archiver');

        $this->app->singleton(StorageManager::class, function ($app) {
            return new StorageManager($app['config']['db-archiver.storage']);
        });

        $this->app->singleton(ArchiveService::class, function ($app) {
            return new ArchiveService(
                $app->make(StorageManager::class),
                $app['config']['db-archiver']
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/db-archiver.php' => config_path('db-archiver.php'),
        ], 'db-archiver-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'db-archiver-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ArchiveTableCommand::class,
                ArchiveAllCommand::class,
                ArchiveStatusCommand::class,
            ]);
        }

        $this->scheduleArchival();
    }

    /**
     * Schedule archival tasks based on configuration.
     */
    protected function scheduleArchival(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $tables = config('db-archiver.tables', []);

            foreach ($tables as $table => $config) {
                if (!($config['enabled'] ?? false)) {
                    continue;
                }

                $scheduleFrequency = $config['schedule'] ?? 'daily';
                $command = "archive:table {$table}";

                $event = match ($scheduleFrequency) {
                    'daily' => $schedule->command($command)->daily(),
                    'weekly' => $schedule->command($command)->weekly(),
                    'monthly' => $schedule->command($command)->monthly(),
                    default => $schedule->command($command)->cron($scheduleFrequency),
                };

                $event->name("archive-{$table}")
                      ->withoutOverlapping()
                      ->runInBackground();
            }
        });
    }
}