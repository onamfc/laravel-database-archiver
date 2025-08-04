<?php

namespace LaravelDbArchiver\Adapters;

use Aws\S3\S3Client;
use LaravelDbArchiver\Exceptions\StorageException;

class S3StorageAdapter
{
    protected S3Client $client;
    protected string $bucket;

    public function __construct(array $config)
    {
        $this->client = new S3Client([
            'version' => 'latest',
            'region' => $config['region'],
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
            'endpoint' => $config['endpoint'] ?? null,
            'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? false,
        ]);

        $this->bucket = $config['bucket'];
    }

    /**
     * Store data to S3.
     */
    public function store(string $path, string $data): bool
    {
        try {
            $result = $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $path,
                'Body' => $data,
                'ServerSideEncryption' => 'AES256',
                'StorageClass' => 'STANDARD_IA', // For cold storage
            ]);

            return !empty($result['ETag']);
        } catch (\Exception $e) {
            throw new StorageException("S3 storage failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if file exists in S3.
     */
    public function exists(string $path): bool
    {
        try {
            return $this->client->doesObjectExist($this->bucket, $path);
        } catch (\Exception $e) {
            throw new StorageException("S3 existence check failed: " . $e->getMessage(), 0, $e);
        }
    }
}