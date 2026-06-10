<?php

use Illuminate\Support\Facades\Route;
use Korioinc\ExceptionViewer\Http\Controllers\ExceptionViewerAllController;
use Korioinc\ExceptionViewer\Http\Controllers\ExceptionViewerDeleteController;
use Korioinc\ExceptionViewer\Http\Controllers\ExceptionViewerIndexController;
use Korioinc\ExceptionViewer\Http\Controllers\ExceptionViewerPurgeController;
use Korioinc\ExceptionViewer\Http\Controllers\ExceptionViewerShowController;
use Korioinc\ExceptionViewer\Http\Controllers\ExceptionViewerSummaryController;

Route::middleware(config('exception-viewer.middleware', []))
    ->prefix(trim((string) config('exception-viewer.route_path', 'exception-viewer'), '/'))
    ->group(function (): void {
        Route::get('/', ExceptionViewerIndexController::class)
            ->name('exception-viewer.index');

        Route::post('/purge', ExceptionViewerPurgeController::class)
            ->name('exception-viewer.purge');

        Route::post('/entries/{id}/delete', ExceptionViewerDeleteController::class)
            ->whereNumber('id')
            ->name('exception-viewer.delete');

        Route::get('/all', ExceptionViewerAllController::class)
            ->name('exception-viewer.all');

        Route::get('/json', ExceptionViewerSummaryController::class)
            ->name('exception-viewer.summary');

        Route::get('/{key}', ExceptionViewerShowController::class)
            ->where('key', '[A-Fa-f0-9]{64}')
            ->name('exception-viewer.show');
    });
