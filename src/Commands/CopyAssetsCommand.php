<?php

namespace Atum\NativephpLoader\Commands;

use Native\Mobile\Plugins\Commands\NativePluginHookCommand;

/**
 * Copy assets hook command for NativephpLoader plugin.
 *
 * This hook runs during the copy_assets phase of the build process.
 * Use it to copy ML models, binary files, or other assets that need
 * to be in specific locations in the native project.
 *
 * @see \Native\Mobile\Plugins\Commands\NativePluginHookCommand
 */
class CopyAssetsCommand extends NativePluginHookCommand
{
    protected $signature = 'nativephp:nativephp-loader:copy-assets';

    protected $description = 'Copy assets for NativephpLoader plugin';

    public function handle(): int
    {
        // Example: Copy different files based on platform
        if ($this->isAndroid()) {
            $this->copyAndroidAssets();
        }

        if ($this->isIos()) {
            $this->copyIosAssets();
        }

        return self::SUCCESS;
    }

    /**
     * Copy assets for Android build
     */
    protected function copyAndroidAssets(): void
    {
        $lottiePath = $this->getLottiePath();

        if (!$lottiePath) {
            return;
        }

        $filename = basename($lottiePath);
        $this->copyToAndroidAssets($lottiePath, "animations/{$filename}");
        $this->info("Copied {$filename} to Android assets");
    }

    /**
     * Copy assets for iOS build
     */
    protected function copyIosAssets(): void
    {
        $lottiePath = $this->getLottiePath();

        if (!$lottiePath) {
            return;
        }

        $filename = basename($lottiePath);
        $this->copyToIosBundle($lottiePath, "animations/{$filename}");
        $this->info("Copied {$filename} to iOS bundle");
    }

    /**
     * Get the Lottie file path from ENV or default to demo.lottie
     */
    protected function getLottiePath(): ?string
    {
        // Check for custom path in ENV
        $lottiePath = env('LOADER_LOTTIE_PATH');

        // Fall back to demo.lottie in Laravel resources
        if (!$lottiePath) {
            $lottiePath = resource_path('animations/demo.lottie');
            $this->info('LOADER_LOTTIE_PATH not set, using default demo.lottie');
        }

        // Verify file exists
        if (!file_exists($lottiePath)) {
            $this->warn("Lottie file not found: {$lottiePath}");
            if (!env('LOADER_LOTTIE_PATH')) {
                $this->warn("Run 'php artisan vendor:publish --tag=nativephp-loader-animations' to publish demo animations");
            }
            return null;
        }

        return $lottiePath;
    }
}