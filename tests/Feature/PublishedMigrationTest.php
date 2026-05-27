<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates the exception logs table on the configured database connection when publishing migrations', function () {
    $defaultPath = tempnam(sys_get_temp_dir(), 'exception-viewer-default-');
    $secondaryPath = tempnam(sys_get_temp_dir(), 'exception-viewer-secondary-');

    expect($defaultPath)->not->toBeFalse()
        ->and($secondaryPath)->not->toBeFalse();

    config()->set('database.connections.migration_default', [
        'driver' => 'sqlite',
        'database' => $defaultPath,
        'prefix' => '',
    ]);
    config()->set('database.connections.exception_logs', [
        'driver' => 'sqlite',
        'database' => $secondaryPath,
        'prefix' => '',
    ]);
    config()->set('database.default', 'migration_default');
    config()->set('exception-viewer.database_connection', 'exception_logs');

    DB::purge('migration_default');
    DB::purge('exception_logs');

    $migration = include __DIR__.'/../../database/migrations/create_exception_logs_table.php.stub';

    try {
        $migration->up();

        $sourceColumns = array_values(array_filter(
            Schema::connection('exception_logs')->getColumnListing('exception_logs'),
            static fn (string $column): bool => str_starts_with($column, 'source_'),
        ));

        expect(Schema::connection('exception_logs')->hasTable('exception_logs'))->toBeTrue()
            ->and(Schema::connection('migration_default')->hasTable('exception_logs'))->toBeFalse()
            ->and(Schema::connection('exception_logs')->hasColumns('exception_logs', [
                'source_key',
                'received_at',
            ]))->toBeTrue()
            ->and(Schema::connection('exception_logs')->hasIndex('exception_logs', 'exception_logs_source_key_key_unique', 'unique'))->toBeTrue()
            ->and(Schema::connection('exception_logs')->hasIndex('exception_logs', 'exception_logs_source_key_index'))->toBeTrue()
            ->and(Schema::connection('exception_logs')->hasIndex('exception_logs', 'exception_logs_name_index'))->toBeTrue()
            ->and(Schema::connection('exception_logs')->hasIndex('exception_logs', 'exception_logs_latest_at_index'))->toBeTrue()
            ->and(Schema::connection('exception_logs')->hasIndex('exception_logs', 'exception_logs_count_index'))->toBeTrue()
            ->and($sourceColumns)->toBe(['source_key']);
    } finally {
        Schema::connection('migration_default')->dropIfExists('exception_logs');
        Schema::connection('exception_logs')->dropIfExists('exception_logs');
        DB::purge('migration_default');
        DB::purge('exception_logs');
        config()->set('database.default', 'testing');
        config()->set('exception-viewer.database_connection', null);
        @unlink($defaultPath);
        @unlink($secondaryPath);
    }
});
