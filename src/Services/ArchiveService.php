<?php

namespace LaravelDbArchiver\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LaravelDbArchiver\Exceptions\ArchiveException;
use LaravelDbArchiver\Formatters\JsonFormatter;
use LaravelDbArchiver\Formatters\ParquetFormatter;
use LaravelDbArchiver\Models\ArchiveLog;

class ArchiveService
{
    protected StorageManager $storageManager;
    protected array $config;

    public function __construct(StorageManager $storageManager, array $config)
    {
        $this->storageManager = $storageManager;
        $this->config = $config;
    }

    /**
     * Archive records from a specific table.
     */
    public function archiveTable(string $table, array $options = []): array
    {
        $startTime = microtime(true);
        $tableConfig = $this->getTableConfig($table);
        
        if (!$tableConfig['enabled']) {
            throw new ArchiveException("Archival is disabled for table: {$table}");
        }

        try {
            $this->logOperation('info', "Starting archival for table: {$table}");

            $query = $this->buildQuery($table, $tableConfig);
            $totalRecords = $query->count();

            if ($totalRecords === 0) {
                $this->logOperation('info', "No records to archive for table: {$table}");
                return $this->buildResult($table, 0, 0, microtime(true) - $startTime);
            }

            $archivedCount = $this->processRecords($table, $query, $tableConfig);
            
            $duration = microtime(true) - $startTime;
            $result = $this->buildResult($table, $totalRecords, $archivedCount, $duration);

            $this->logArchiveOperation($table, $result);
            $this->logOperation('info', "Completed archival for table: {$table}. Archived: {$archivedCount} records");

            return $result;

        } catch (\Exception $e) {
            $this->logOperation('error', "Failed to archive table {$table}: " . $e->getMessage());
            throw new ArchiveException("Archive failed for table {$table}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Archive all configured tables.
     */
    public function archiveAll(): array
    {
        $results = [];
        $tables = array_keys($this->config['tables'] ?? []);

        foreach ($tables as $table) {
            if ($this->getTableConfig($table)['enabled'] ?? false) {
                try {
                    $results[$table] = $this->archiveTable($table);
                } catch (\Exception $e) {
                    $results[$table] = [
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                        'archived_count' => 0,
                        'total_records' => 0,
                        'duration' => 0,
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Get archival status for all tables.
     */
    public function getStatus(): array
    {
        $status = [];
        $tables = array_keys($this->config['tables'] ?? []);

        foreach ($tables as $table) {
            $lastLog = ArchiveLog::where('table_name', $table)
                                 ->latest()
                                 ->first();

            $status[$table] = [
                'enabled' => $this->getTableConfig($table)['enabled'] ?? false,
                'last_archived' => $lastLog?->created_at,
                'last_status' => $lastLog?->status,
                'last_archived_count' => $lastLog?->archived_count ?? 0,
                'pending_records' => $this->getPendingRecordsCount($table),
            ];
        }

        return $status;
    }

    /**
     * Build query for records to archive.
     */
    protected function buildQuery(string $table, array $config): Builder
    {
        $query = DB::table($table);

        // Apply main criteria
        if (isset($config['criteria'])) {
            $criteria = $config['criteria'];
            $value = $this->resolveCriteriaValue($criteria['value']);
            $this->guardAgainstFutureCutoff($table, $criteria, $value, $config);
            $query->where($criteria['column'], $criteria['operator'], $value);
        }

        // Apply additional criteria
        if (isset($config['additional_criteria'])) {
            foreach ($config['additional_criteria'] as $criterion) {
                $value = $this->resolveCriteriaValue($criterion['value']);
                $this->guardAgainstFutureCutoff($table, $criterion, $value, $config);
                $query->where($criterion['column'], $criterion['operator'], $value);
            }
        }

        return $query;
    }

    /**
     * Process records in chunks.
     */
    protected function processRecords(string $table, Builder $query, array $config): int
    {
        $chunkSize = $this->config['chunk_size'] ?? 1000;
        $format = $config['format'] ?? $this->config['default_format'];
        $storage = $config['storage'] ?? $this->config['default_storage'];
        $archivedCount = 0;

        /**
         * chunkById, not chunk. chunk() pages with OFFSET, and when delete_after_archive removes a
         * batch the rows below it shift up by that many positions, so the next offset steps clean
         * over them. Archiving with deletion enabled silently skipped roughly half the eligible
         * rows in alternating blocks. chunkById pages on "id greater than the last one seen", which
         * deleting the rows behind it cannot disturb. It applies its own ordering on that column,
         * so the explicit orderBy is no longer needed.
         */
        $query->chunkById($chunkSize, function ($records) use ($table, $config, $format, $storage, &$archivedCount) {
            $data = $records->toArray();
            $filename = $this->generateFilename($table, $config, $format);
            
            $formattedData = $this->formatData($data, $format);
            $this->storageManager->store($storage, $filename, $formattedData);

            if ($config['delete_after_archive'] ?? false) {
                $ids = collect($data)->pluck('id')->toArray();
                DB::table($table)->whereIn('id', $ids)->delete();
            }

            $archivedCount += count($data);
        });

        return $archivedCount;
    }

    /**
     * Format data according to the specified format.
     */
    protected function formatData(array $data, string $format): string
    {
        return match ($format) {
            'json' => (new JsonFormatter())->format($data),
            'parquet' => (new ParquetFormatter())->format($data),
            default => throw new ArchiveException("Unsupported format: {$format}"),
        };
    }

    /**
     * Generate filename for archived data.
     */
    protected function generateFilename(string $table, array $config, string $format): string
    {
        $path = $config['path'] ?? "archives/{table}/{date}";
        $extension = $format === 'parquet' ? 'parquet' : 'json';
        
        $path = str_replace(['{table}', '{date}'], [$table, now()->format('Y-m-d')], $path);
        
        return "{$path}/" . uniqid() . ".{$extension}";
    }

    /**
     * Resolve criteria value (handle Carbon-parseable strings).
     */
    protected function resolveCriteriaValue($value)
    {
        if ($value instanceof \Closure) {
            return $value();
        }

        /**
         * Handed to Carbon whole. This used to split the phrase and call
         * Carbon::now()->sub('1', 'month ago'), but sub() takes (unit, value), so the arguments
         * arrived reversed and "1 month ago" resolved to one month in the FUTURE. Every row then
         * satisfied a "created_at <" criteria, and a table configured with delete_after_archive
         * lost its entire contents rather than only what had expired.
         *
         * The pattern is anchored so only relative phrases are parsed. Anything else - an explicit
         * date, a number, a status string - passes through untouched, since Carbon would happily
         * turn a bare "100" into a timestamp.
         */
        if (is_string($value)
            && preg_match('/^\s*\d+\s+(second|minute|hour|day|week|month|year)s?\s+ago\s*$/i', $value)) {
            return Carbon::parse($value);
        }

        return $value;
    }

    /**
     * Refuse a cutoff that would sweep up rows which have not expired.
     *
     * A "column < date" criteria is asking for everything older than that date. If the date lands
     * in the future it selects the whole table, and with delete_after_archive that empties it. That
     * is never what a retention rule means, so it is treated as a misconfiguration rather than
     * carried out.
     */
    protected function guardAgainstFutureCutoff(string $table, array $criteria, $value, array $config): void
    {
        if (!($config['delete_after_archive'] ?? false)) {
            return;
        }

        if (!in_array($criteria['operator'] ?? '=', ['<', '<='], true)) {
            return;
        }

        /**
         * A criteria value reaches here either already resolved to a date, or as a literal date
         * string straight from the config. Both forms are checked; anything that is not a date at
         * all - a number, a status - is none of this guard's business.
         */
        if ($value instanceof \DateTimeInterface) {
            $cutoff = Carbon::instance($value);
        } elseif (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}([ T]|$)/', $value)) {
            $cutoff = Carbon::parse($value);
        } else {
            return;
        }

        if ($cutoff->isFuture()) {
            throw new ArchiveException(
                "Refusing to archive {$table}: the criteria on {$criteria['column']} resolves to "
                . $cutoff->toDateTimeString() . ', which is in the future. Every row '
                . 'would match and delete_after_archive would empty the table. Check the criteria value.'
            );
        }
    }

    /**
     * Get table configuration.
     */
    protected function getTableConfig(string $table): array
    {
        $config = $this->config['tables'][$table] ?? [];
        
        if (empty($config)) {
            throw new ArchiveException("No configuration found for table: {$table}");
        }

        return $config;
    }

    /**
     * Get count of pending records for archival.
     */
    protected function getPendingRecordsCount(string $table): int
    {
        try {
            $config = $this->getTableConfig($table);
            return $this->buildQuery($table, $config)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Build result array.
     */
    protected function buildResult(string $table, int $total, int $archived, float $duration): array
    {
        return [
            'table' => $table,
            'status' => 'success',
            'total_records' => $total,
            'archived_count' => $archived,
            'duration' => round($duration, 2),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Log archive operation to database.
     */
    protected function logArchiveOperation(string $table, array $result): void
    {
        ArchiveLog::create([
            'table_name' => $table,
            'status' => $result['status'],
            'total_records' => $result['total_records'],
            'archived_count' => $result['archived_count'],
            'duration' => $result['duration'],
            'metadata' => json_encode($result),
        ]);
    }

    /**
     * Log operation message.
     */
    protected function logOperation(string $level, string $message): void
    {
        if ($this->config['logging']['enabled'] ?? true) {
            Log::channel($this->config['logging']['channel'] ?? 'daily')
               ->log($level, $message);
        }
    }
}