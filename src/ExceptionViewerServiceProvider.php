<?php

namespace Korioinc\ExceptionViewer;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Korioinc\ExceptionViewer\Alarm\Channels\DiscordExceptionAlarmChannel;
use Korioinc\ExceptionViewer\Alarm\Contracts\ExceptionAlarmChannel;
use Korioinc\ExceptionViewer\Alarm\ExceptionAlarmHandler;
use Korioinc\ExceptionViewer\Alarm\ExceptionAlarmNotifier;
use Korioinc\ExceptionViewer\Context\QueueContextStore;
use Korioinc\ExceptionViewer\Context\RequestContextResolver;
use Korioinc\ExceptionViewer\Recording\ExceptionFingerprint;
use Korioinc\ExceptionViewer\Recording\ExceptionLogRecorder;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Throwable;

class ExceptionViewerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-exception-viewer')
            ->hasConfigFile()
            ->hasViews()
            ->hasRoute('web')
            ->hasMigration('create_exception_logs_table');
    }

    public function packageRegistered()
    {
        $this->app->singleton(ExceptionFingerprint::class);
        $this->app->singleton(QueueContextStore::class);
        $this->app->singleton(RequestContextResolver::class);
        $this->app->singleton(ExceptionLogRecorder::class);
        $this->app->singleton(DiscordExceptionAlarmChannel::class);
        $this->app->tag([DiscordExceptionAlarmChannel::class], ExceptionAlarmChannel::class);
        $this->app->singleton(ExceptionAlarmNotifier::class, function ($app): ExceptionAlarmNotifier {
            return new ExceptionAlarmNotifier(
                $app->make(Dispatcher::class),
                $app->tagged(ExceptionAlarmChannel::class),
            );
        });
        $this->app->singleton(ExceptionAlarmHandler::class);
    }

    public function packageBooted()
    {
        Queue::before(function (JobProcessing $event): void {
            $this->app->make(QueueContextStore::class)->capture($event->job);
        });

        Queue::after(function (JobProcessed $event): void {
            $this->app->make(QueueContextStore::class)->complete($event->job);
        });

        Queue::exceptionOccurred(function (JobExceptionOccurred $event): void {
            $this->app->make(QueueContextStore::class)->markException($event->job, $event->exception);
        });

        Exceptions::reportable(function (Throwable $throwable): void {
            $fingerprintKey = $this->app->make(ExceptionLogRecorder::class)->record($throwable);

            $this->app->make(ExceptionAlarmHandler::class)->handle($throwable, $fingerprintKey);
            $this->app->make(QueueContextStore::class)->completeReportedException($throwable);
        });
    }
}
