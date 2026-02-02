<?php

namespace Atum\NativephpLoader;

use Illuminate\Support\ServiceProvider;
use Atum\NativephpLoader\Commands\CopyAssetsCommand;
use Atum\NativephpLoader\Commands\PreCompileCommand;

class NativephpLoaderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/nativephp/loader.php', 'nativephp.loader'
        );

        $this->app->singleton(NativephpLoader::class, function () {
            return new NativephpLoader();
        });
    }

    public function boot(): void
    {
        // Register plugin hook commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                PreCompileCommand::class,
                CopyAssetsCommand::class,
            ]);

            // Publish config
            $this->publishes([
                __DIR__.'/../config/nativephp/loader.php' => config_path('nativephp/loader.php'),
            ], 'nativephp-loader-config');

            // Publish animation files
            $this->publishes([
                __DIR__.'/../resources/animations' => resource_path('animations'),
            ], 'nativephp-loader-animations');

            // Publish all assets together
            $this->publishes([
                __DIR__.'/../config/nativephp/loader.php' => config_path('nativephp/loader.php'),
                __DIR__.'/../resources/animations' => resource_path('animations'),
            ], 'nativephp-loader');
        }
    }
}
