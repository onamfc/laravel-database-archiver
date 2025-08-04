<?php

namespace onamfc\LaravelDbArchiver\Commands;

use Illuminate\Console\Command;
use onamfc\LaravelDbArchiver\Services\ArchiveService;

class ArchiveTableCommand extends Command
{
    protected $signature = 'archive:table {table : The table to archive}
                            {--dry-run : Show what would be archived without actually archiving}
                            {--force : Force archival even if table is disabled}';

    protected $description = 'Archive records from a specific table to cold storage';

    public function handle(ArchiveService $archiveService): int
    {
        $table = $this->argument('table');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("Processing archival for table: {$table}");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No actual archival will be performed');
            // Add dry run logic here
            return self::SUCCESS;
        }

        try {
            $result = $archiveService->archiveTable($table, [
                'force' => $force,
            ]);

            $this->displayResult($result);
            
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Archive failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    protected function displayResult(array $result): void
    {
        $this->newLine();
        $this->info('Archive completed successfully!');
        $this->table(['Metric', 'Value'], [
            ['Table', $result['table']],
            ['Total Records', number_format($result['total_records'])],
            ['Archived Count', number_format($result['archived_count'])],
            ['Duration', $result['duration'] . ' seconds'],
            ['Timestamp', $result['timestamp']],
        ]);
    }
}