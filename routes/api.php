<?php

use Illuminate\Support\Facades\Route;
use Korioinc\ExceptionViewer\Http\Controllers\ExceptionReceiverController;

Route::middleware(config('exception-viewer.receiver.middleware', ['api']))
    ->prefix(trim((string) config('exception-viewer.receiver.route_path', 'api/exception-viewer/exceptions'), '/'))
    ->post('/', ExceptionReceiverController::class)
    ->name('exception-viewer.receiver');
