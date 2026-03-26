<?php

use Illuminate\Support\Facades\Route;
use Korioinc\ExceptionViewer\Http\Controllers\ExceptionViewerAllController;
use Korioinc\ExceptionViewer\Http\Controllers\ExceptionViewerIndexController;
use Korioinc\ExceptionViewer\Http\Controllers\ExceptionViewerPurgeController;
use Korioinc\ExceptionViewer\Http\Controllers\ExceptionViewerShowController;

Route::middleware(config('exception-viewer.middleware', []))
    ->prefix(trim((string) config('exception-viewer.route_path', 'exception-viewer'), '/'))
    ->group(function (): void {
        Route::get('/', ExceptionViewerIndexController::class)
            ->name('exception-viewer.index');

        Route::post('/purge', ExceptionViewerPurgeController::class)
            ->name('exception-viewer.purge');

        Route::get('/all', ExceptionViewerAllController::class)
            ->name('exception-viewer.all');

        Route::get('/{key}', ExceptionViewerShowController::class)
            ->where('key', '[A-Fa-f0-9]{64}')
            ->name('exception-viewer.show');
    });
