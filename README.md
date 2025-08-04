## Laravel Database Archiver
[![Packagist License](https://img.shields.io/badge/Licence-MIT-blue)](http://choosealicense.com/licenses/mit/)

A comprehensive Laravel package for efficient database record archival to cold storage systems like AWS S3, with support for multiple formats and automated scheduling.

## Features

- **Multi-storage Support**: AWS S3, local filesystem, and extensible for other providers
- **Multiple Formats**: JSON and Parquet export formats
- **Configurable Criteria**: Flexible record selection based on age, status, or custom conditions
- **Scheduled Archival**: Integration with Laravel Task Scheduler
- **Memory Efficient**: Processes large datasets in configurable chunks
- **Comprehensive Logging**: Detailed operation logs and database tracking
- **Artisan Commands**: Easy-to-use CLI interface
- **Multi-table Support**: Archive multiple tables with different configurations

## Installation

Install the package via Composer:

```bash
composer require onamfc/laravel-db-archiver
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=db-archiver-config
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag=db-archiver-migrations
php artisan migrate
```

## Configuration

Configure your archival settings in `config/db-archiver.php`:

### Basic Storage Configuration

```php
'storage' => [
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'bucket' => env('DB_ARCHIVER_S3_BUCKET'),
    ],
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app/archives'),
    ],
],
```

### Table Configuration

```php
'tables' => [
    'users' => [
        'enabled' => true,
        'criteria' => [
            'column' => 'created_at',
            'operator' => '<',
            'value' => '6 months ago',
        ],
        'format' => 'json',
        'storage' => 's3',
        'path' => 'archives/users/{date}',
        'schedule' => 'daily',
        'delete_after_archive' => false,
    ],
    'logs' => [
        'enabled' => true,
        'criteria' => [
            'column' => 'created_at',
            'operator' => '<',
            'value' => '3 months ago',
        ],
        'format' => 'parquet',
        'storage' => 's3',
        'path' => 'archives/logs/{date}',
        'schedule' => 'weekly',
        'delete_after_archive' => true,
        'additional_criteria' => [
            ['column' => 'level', 'operator' => '=', 'value' => 'debug'],
        ],
    ],
],
```

### Environment Variables

Add these to your `.env` file:

```env
# Storage Configuration
DB_ARCHIVER_STORAGE=s3
DB_ARCHIVER_S3_BUCKET=your-archive-bucket
DB_ARCHIVER_FORMAT=json
DB_ARCHIVER_CHUNK_SIZE=1000

# AWS Credentials (if using S3)
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1

# Logging
DB_ARCHIVER_LOGGING=true
DB_ARCHIVER_LOG_CHANNEL=daily
```

## Usage

### Artisan Commands

#### Archive a Single Table

```bash
# Archive records from the users table
php artisan archive:table users

# Dry run to see what would be archived
php artisan archive:table users --dry-run

# Force archival even if disabled in config
php artisan archive:table users --force
```

#### Archive All Configured Tables

```bash
# Archive all enabled tables
php artisan archive:all

# Dry run for all tables
php artisan archive:all --dry-run
```

#### Check Archival Status

```bash
# Status for all tables
php artisan archive:status

# Status for specific table
php artisan archive:status users
```

### Programmatic Usage

```php
use onamfc\LaravelDbArchiver\Services\ArchiveService;

class YourController extends Controller
{
    public function archiveData(ArchiveService $archiveService)
    {
        // Archive a specific table
        $result = $archiveService->archiveTable('users');
        
        // Archive all configured tables
        $results = $archiveService->archiveAll();
        
        // Get status
        $status = $archiveService->getStatus();
        
        return response()->json($result);
    }
}
```

### Scheduled Archival

The package automatically registers scheduled tasks based on your table configurations. The following schedules are supported:

- `daily` - Runs daily at midnight
- `weekly` - Runs weekly on Sunday at midnight
- `monthly` - Runs monthly on the 1st at midnight
- Custom cron expressions (e.g., `0 2 * * *` for 2 AM daily)

## Advanced Configuration

### Custom Criteria Values

You can use dynamic values in your criteria:

```php
'criteria' => [
    'column' => 'created_at',
    'operator' => '<',
    'value' => '6 months ago', // Carbon-parseable string
],

// Or use a closure for complex logic
'criteria' => [
    'column' => 'status',
    'operator' => '=',
    'value' => function () {
        return config('app.archive_status');
    },
],
```

### Multiple Criteria

```php
'additional_criteria' => [
    ['column' => 'status', 'operator' => '=', 'value' => 'inactive'],
    ['column' => 'last_login', 'operator' => '<', 'value' => '1 year ago'],
],
```

### Custom Storage Paths

Use dynamic placeholders in storage paths:

```php
'path' => 'archives/{table}/{date}', // archives/users/2024-01-15
'path' => 'backups/{table}/year={Y}/month={m}', // backups/users/year=2024/month=01
```

## Storage Adapters

### AWS S3

```php
'storage' => [
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('DB_ARCHIVER_S3_BUCKET'),
        'endpoint' => env('AWS_ENDPOINT'), // For S3-compatible services
    ],
],
```

### Local Storage

```php
'storage' => [
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app/archives'),
    ],
],
```

## Data Formats

### JSON Format

Stores data as formatted JSON files:

```json
[
    {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "created_at": "2024-01-01T00:00:00Z"
    }
]
```

### Parquet Format

Stores data in Parquet format for efficient compression and analytics.

## Monitoring and Logging

### Database Logging

All archival operations are logged to the `archive_logs` table:

```php
$logs = \onamfc\LaravelDbArchiver\Models\ArchiveLog::where('table_name', 'users')
    ->latest()
    ->get();
```

### File Logging

Operations are also logged to your configured log channel:

```php
'logging' => [
    'enabled' => true,
    'channel' => 'daily',
    'level' => 'info',
],
```

## Error Handling

The package includes comprehensive error handling:

- **Storage connectivity issues**
- **Invalid configurations**
- **Data transformation errors**
- **Permission problems**
- **Memory limitations**

Errors are logged and can trigger notifications if configured.

## Security Considerations

- Use IAM roles with minimal required permissions for S3 access
- Enable server-side encryption for stored archives
- Regularly rotate access keys
- Monitor access logs for archived data
- Consider using S3 bucket policies for additional security

## Performance Optimization

- Adjust `chunk_size` based on your available memory
- Use appropriate S3 storage classes (Standard-IA, Glacier)
- Schedule archival during low-traffic periods
- Monitor database performance during archival operations
- Consider using database indexes on archival criteria columns

## Testing

```bash
# Run package tests
vendor/bin/phpunit

# Test with specific configuration
php artisan archive:table users --dry-run
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
