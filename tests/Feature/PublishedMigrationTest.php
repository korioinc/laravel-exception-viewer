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

        expect(Schema::connection('exception_logs')->hasTable('exception_logs'))->toBeTrue()
            ->and(Schema::connection('migration_default')->hasTable('exception_logs'))->toBeFalse();
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
