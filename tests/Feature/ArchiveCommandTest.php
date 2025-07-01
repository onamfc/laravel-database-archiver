<?php

namespace YourVendor\LaravelDbArchiver\Tests\Feature;

use Orchestra\Testbench\TestCase;
use YourVendor\LaravelDbArchiver\DbArchiverServiceProvider;

class ArchiveCommandTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [DbArchiverServiceProvider::class];
    }

    public function test_archive_status_command_exists()
    {
        $this->artisan('archive:status')
             ->assertExitCode(0);
    }

    public function test_archive_table_command_with_dry_run()
    {
        $this->artisan('archive:table', ['table' => 'test', '--dry-run' => true])
             ->assertExitCode(0);
    }
}