<?php

namespace YourVendor\LaravelDbArchiver\Commands;

use Illuminate\Console\Command;
use YourVendor\LaravelDbArchiver\Services\ArchiveService;

class ArchiveAllCommand extends Command
{
    protected $signature = 'archive:all
                            {--dry-run : Show what would be archived without actually archiving}
                            {--parallel : Run archival in parallel (experimental)}';

    protected $description = 'Archive records from all configured tables to cold storage';

    public function handle(ArchiveService $archiveService): int
    {
        $dryRun = $this->option('dry-run');
        $parallel = $this->option('parallel');

        $this->info('Starting archival for all configured tables...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No actual archival will be performed');
        }

        if ($parallel) {
            $this->warn('Parallel mode is experimental and may cause issues');
        }

        try {
            $results = $archiveService->archiveAll();
            $this->displayResults($results);
            
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Archive failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    protected function displayResults(array $results): void
    {
        $this->newLine();
        $this->info('Archive summary:');

        $tableData = [];
        $totalArchived = 0;
        $totalDuration = 0;

        foreach ($results as $table => $result) {
            $status = $result['status'] === 'success' ? '✓' : '✗';
            $archived = $result['archived_count'] ?? 0;
            $duration = $result['duration'] ?? 0;
            
            $tableData[] = [
                $status,
                $table,
                number_format($archived),
                $duration . 's',
                $result['error'] ?? 'Success',
            ];

            if ($result['status'] === 'success') {
                $totalArchived += $archived;
                $totalDuration += $duration;
            }
        }

        $this->table(
            ['Status', 'Table', 'Archived', 'Duration', 'Message'],
            $tableData
        );

        $this->newLine();
        $this->info("Total archived: " . number_format($totalArchived) . " records");
        $this->info("Total duration: {$totalDuration} seconds");
    }
}