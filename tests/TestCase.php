<?php

namespace Korioinc\ExceptionViewer\Tests;

use Illuminate\Support\Facades\Schema;
use Korioinc\ExceptionViewer\ExceptionViewerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            ExceptionViewerServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('cache.default', 'array');
        config()->set('session.driver', 'array');
    }

    protected function defineDatabaseMigrations()
    {
        (include __DIR__.'/../database/migrations/create_exception_logs_table.php.stub')->up();
    }

    protected function destroyDatabaseMigrations()
    {
        Schema::dropIfExists('exception_logs');
    }
}
