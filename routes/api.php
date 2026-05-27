<?php

use Illuminate\Support\Facades\Route;
use Korioinc\ExceptionViewer\Http\Controllers\ExceptionReceiverController;

Route::middleware(config('exception-viewer.receiver.middleware', []))
    ->prefix(trim((string) config('exception-viewer.receiver.route_path', 'exception-viewer/api/exceptions'), '/'))
    ->post('/', ExceptionReceiverController::class)
    ->name('exception-viewer.receiver');
