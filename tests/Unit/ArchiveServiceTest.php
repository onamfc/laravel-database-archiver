<?php

namespace YourVendor\LaravelDbArchiver\Tests\Unit;

use PHPUnit\Framework\TestCase;
use YourVendor\LaravelDbArchiver\Services\ArchiveService;
use YourVendor\LaravelDbArchiver\Services\StorageManager;

class ArchiveServiceTest extends TestCase
{
    public function test_archive_service_can_be_instantiated()
    {
        $storageManager = $this->createMock(StorageManager::class);
        $config = [];
        
        $service = new ArchiveService($storageManager, $config);
        
        $this->assertInstanceOf(ArchiveService::class, $service);
    }
    
    public function test_get_status_returns_array()
    {
        $storageManager = $this->createMock(StorageManager::class);
        $config = [
            'tables' => [
                'users' => ['enabled' => true],
            ],
        ];
        
        $service = new ArchiveService($storageManager, $config);
        $status = $service->getStatus();
        
        $this->assertIsArray($status);
        $this->assertArrayHasKey('users', $status);
    }
}