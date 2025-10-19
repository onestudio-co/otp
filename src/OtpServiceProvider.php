<?php

declare(strict_types=1);
 
namespace OneStudio\Otp;
 
use Illuminate\Support\ServiceProvider;

final class OtpServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/config/otp.php', 'otp');

        $this->app->singleton(OtpManager::class, function ($app) {
            return new OtpManager($app);
        });

        $this->app->singleton(OtpService::class, function ($app) {
            return new OtpService($app->make(OtpManager::class));
        });

        $this->app->alias(OtpManager::class, 'otp');
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/otp.php' => $this->app->configPath('otp.php'),
        ], 'otp-config');
    }
}
