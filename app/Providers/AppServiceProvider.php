<?php

namespace App\Providers;

use App\Contracts\FileRemover;
use App\Contracts\FileUploader;
use App\Services\FileStorage\FileDeletionService;
use App\Services\FileStorage\FileUploadService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FileUploader::class, fn (Application $app) => new FileUploadService(
            disk: $app['filesystem']->disk(config('filestorage.disk')),
            directory: config('filestorage.directory'),
            ttlHours: config('filestorage.ttl_hours'),
        ));

        $this->app->bind(FileRemover::class, fn (Application $app) => new FileDeletionService(
            disk: $app['filesystem']->disk(config('filestorage.disk')),
            events: $app['events'],
            logger: $app['log'],
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
    }
}
