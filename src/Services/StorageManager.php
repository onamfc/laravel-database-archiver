<?php

namespace LaravelDbArchiver\Services;

use Illuminate\Support\Facades\Storage;
use LaravelDbArchiver\Adapters\LocalStorageAdapter;
use LaravelDbArchiver\Adapters\S3StorageAdapter;
use LaravelDbArchiver\Exceptions\StorageException;

class StorageManager
{
    protected array $config;
    protected array $adapters = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Store data using the specified storage driver.
     */
    public function store(string $driver, string $path, string $data): bool
    {
        $adapter = $this->getAdapter($driver);
        
        try {
            return $adapter->store($path, $data);
        } catch (\Exception $e) {
            throw new StorageException("Failed to store data: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get storage adapter for the specified driver.
     */
    protected function getAdapter(string $driver)
    {
        if (!isset($this->adapters[$driver])) {
            $this->adapters[$driver] = $this->createAdapter($driver);
        }

        return $this->adapters[$driver];
    }

    /**
     * Create storage adapter based on driver configuration.
     */
    protected function createAdapter(string $driver)
    {
        $config = $this->config[$driver] ?? null;

        if (!$config) {
            throw new StorageException("Storage configuration not found for driver: {$driver}");
        }

        return match ($config['driver']) {
            's3' => new S3StorageAdapter($config),
            'local' => new LocalStorageAdapter($config),
            default => throw new StorageException("Unsupported storage driver: {$config['driver']}"),
        };
    }
}