<?php

namespace LaravelDbArchiver\Commands;

use Illuminate\Console\Command;
use LaravelDbArchiver\Services\ArchiveService;

class ArchiveStatusCommand extends Command
{
    protected $signature = 'archive:status {table? : Check status for specific table}';

    protected $description = 'Check the archival status of configured tables';

    public function handle(ArchiveService $archiveService): int
    {
        $table = $this->argument('table');
        
        try {
            $status = $archiveService->getStatus();

            if ($table) {
                if (!isset($status[$table])) {
                    $this->error("Table '{$table}' is not configured for archival.");
                    return self::FAILURE;
                }
                $this->displayTableStatus($table, $status[$table]);
            } else {
                $this->displayAllStatus($status);
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to get status: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    protected function displayTableStatus(string $table, array $status): void
    {
        $this->info("Status for table: {$table}");
        $this->newLine();

        $data = [
            ['Enabled', $status['enabled'] ? 'Yes' : 'No'],
            ['Last Archived', $status['last_archived'] ?? 'Never'],
            ['Last Status', $status['last_status'] ?? 'N/A'],
            ['Last Archived Count', number_format($status['last_archived_count'])],
            ['Pending Records', number_format($status['pending_records'])],
        ];

        $this->table(['Metric', 'Value'], $data);
    }

    protected function displayAllStatus(array $status): void
    {
        $this->info('Archival status for all configured tables:');
        $this->newLine();

        $tableData = [];
        foreach ($status as $table => $tableStatus) {
            $enabled = $tableStatus['enabled'] ? '✓' : '✗';
            $lastArchived = $tableStatus['last_archived'] 
                ? $tableStatus['last_archived']->diffForHumans() 
                : 'Never';
            
            $tableData[] = [
                $table,
                $enabled,
                $lastArchived,
                number_format($tableStatus['pending_records']),
                $tableStatus['last_status'] ?? 'N/A',
            ];
        }

        $this->table(
            ['Table', 'Enabled', 'Last Archived', 'Pending', 'Last Status'],
            $tableData
        );
    }
}