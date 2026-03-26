<?php

use Korioinc\ExceptionViewer\ExceptionViewerServiceProvider;

it('registers a bundled publish tag for config and migrations only', function () {
    $bundle = ExceptionViewerServiceProvider::pathsToPublish(
        ExceptionViewerServiceProvider::class,
        'exception-viewer-install',
    );

    $config = ExceptionViewerServiceProvider::pathsToPublish(
        ExceptionViewerServiceProvider::class,
        'exception-viewer-config',
    );

    $migrations = ExceptionViewerServiceProvider::pathsToPublish(
        ExceptionViewerServiceProvider::class,
        'exception-viewer-migrations',
    );

    $views = ExceptionViewerServiceProvider::pathsToPublish(
        ExceptionViewerServiceProvider::class,
        'exception-viewer-views',
    );

    expect(ExceptionViewerServiceProvider::publishableGroups())->toContain('exception-viewer-install')
        ->and($bundle)->toBe(array_merge($config, $migrations))
        ->and(array_keys($bundle))->not->toContain(...array_keys($views));
});
