<?php

namespace LaravelDbArchiver\Adapters;

use Illuminate\Support\Facades\File;
use LaravelDbArchiver\Exceptions\StorageException;

class LocalStorageAdapter
{
    protected string $root;

    public function __construct(array $config)
    {
        $this->root = $config['root'];
    }

    /**
     * Store data to local filesystem.
     */
    public function store(string $path, string $data): bool
    {
        try {
            $fullPath = $this->root . '/' . $path;
            $directory = dirname($fullPath);

            if (!File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            return File::put($fullPath, $data) !== false;
        } catch (\Exception $e) {
            throw new StorageException("Local storage failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if file exists locally.
     */
    public function exists(string $path): bool
    {
        return File::exists($this->root . '/' . $path);
    }
}