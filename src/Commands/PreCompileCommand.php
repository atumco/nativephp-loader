<?php

namespace Atum\NativephpLoader\Commands;

use Native\Mobile\Plugins\Commands\NativePluginHookCommand;

/**
 * Pre-compile hook command for NativephpLoader plugin.
 *
 * This hook runs during the pre_compile phase of the build process.
 * It reads configuration from config/nativephp/loader.php to determine
 * the Lottie animation file, background color, size, and position.
 *
 * @see \Native\Mobile\Plugins\Commands\NativePluginHookCommand
 */
class PreCompileCommand extends NativePluginHookCommand
{
    protected $signature = 'nativephp:nativephp-loader:pre-compile';

    protected $description = 'Pre-compile hook for NativephpLoader plugin';

    public function handle(): int
    {
        $animationPath = $this->getAnimationPath();

        if (! $animationPath) {
            $this->error('Could not find a valid Lottie animation file.');
            return self::FAILURE;
        }

        $this->info("Using Lottie animation: {$animationPath}");

        // Copy animation to platform-specific locations
        if ($this->isAndroid()) {
            $animationFilename = $this->copyAndroidAnimation($animationPath);
            if ($animationFilename) {
                $this->modifyAndroidSplashScreen($animationFilename);
                $this->modifyAndroidTheme();
            }
        }

        if ($this->isIos()) {
            $animationName = $this->copyIosAnimation($animationPath);
            if ($animationName) {
                $this->modifyIosSplashView($animationName);
                $this->modifyLaunchScreen();
            }
        }

        // Exclude .lottie files from Laravel bundle (already copied to native assets)
        $this->configureCleanupExclusions();

        return self::SUCCESS;
    }

    /**
     * Configure cleanup exclusions to prevent .lottie files from being included in Laravel bundle.
     * These files are already copied to native assets, so including them in the bundle is redundant.
     */
    protected function configureCleanupExclusions(): void
    {
        $exclusions = [
            '*.lottie',
            'animations/*.lottie',
            'animations/**/*.lottie',
        ];

        $currentExclusions = config('nativephp.cleanup_exclude_files', []);
        $newExclusions = array_unique(array_merge($currentExclusions, $exclusions));

        config(['nativephp.cleanup_exclude_files' => $newExclusions]);

        $this->info('Added .lottie exclusions to cleanup_exclude_files config');
    }

    /**
     * Get the path to the Lottie animation file.
     * Reads from config('nativephp.loader.animation_path'), or defaults to demo.lottie.
     */
    protected function getAnimationPath(): ?string
    {
        $customPath = config('nativephp.loader.animation_path');

        // If custom path is provided, validate it
        if ($customPath) {
            $fullPath = base_path($customPath);
            if (file_exists($fullPath)) {
                return $fullPath;
            }

            $this->warn("Custom animation path not found: {$customPath}");
        }

        // Default to the demo.lottie file from Laravel resources
        $defaultPath = resource_path('animations/demo.lottie');
        if (file_exists($defaultPath)) {
            return $defaultPath;
        }

        // Fallback to plugin's bundled demo.lottie
        $pluginDefaultPath = $this->pluginPath() . '/resources/animations/demo.lottie';
        if (file_exists($pluginDefaultPath)) {
            $this->info('Using plugin default animation (Laravel resources not found)');
            return $pluginDefaultPath;
        }

        return null;
    }

    /**
     * Copy animation to Android assets
     * Returns the animation filename for Kotlin reference
     */
    protected function copyAndroidAnimation(string $sourcePath): ?string
    {
        // Extract original filename with extension
        $animationFilename = pathinfo($sourcePath, PATHINFO_BASENAME);

        // Ensure assets directory exists
        $assetsDir = $this->buildPath() . '/app/src/main/assets';
        $this->ensureDirectory($assetsDir);

        $destinationPath = $assetsDir . '/' . $animationFilename;

        if (!copy($sourcePath, $destinationPath)) {
            $this->error("Failed to copy animation to: {$destinationPath}");
            return null;
        }

        $this->info("Android animation copied to: {$destinationPath}");

        return $animationFilename;
    }

    /**
     * Modify MainActivity.kt to integrate Lottie animation in SplashScreen
     */
    protected function modifyAndroidSplashScreen(string $animationFilename): void
    {
        $mainActivityPath = $this->buildPath() . '/app/src/main/java/com/nativephp/mobile/ui/MainActivity.kt';

        if (!file_exists($mainActivityPath)) {
            $this->warn("MainActivity.kt not found at: {$mainActivityPath}");
            return;
        }

        // Get config values
        $backgroundColor = config('nativephp.loader.background_color', '#FFFFFF');
        $size = (float) config('nativephp.loader.size', 0.8);
        $position = config('nativephp.loader.position', 'center');
        $fadeInDuration = (int) config('nativephp.loader.fade_in_duration', 600);

        // Debug output
        $this->info("Loader config - background: {$backgroundColor}, size: {$size}, position: {$position}, fade_in: {$fadeInDuration}ms");

        // Backup original outside of source directory
        $backupDir = $this->buildPath() . '/app/src/main/nativephp-backups';
        $this->ensureDirectory($backupDir);
        $originalPath = $backupDir . '/MainActivity.kt.original';

        if (!file_exists($originalPath)) {
            copy($mainActivityPath, $originalPath);
        }

        // Always start from the original to allow config changes
        $content = file_get_contents($originalPath);

        // Step 1: Add Lottie import if not present
        if (strpos($content, 'import com.airbnb.lottie.compose.*') === false) {
            $content = preg_replace(
                '/(import androidx\.compose\.runtime\.\*)/',
                "$1\nimport com.airbnb.lottie.compose.*",
                $content
            );
        }

        // Step 2: Add animation imports if not present
        if (strpos($content, 'import androidx.compose.animation.core.animateFloatAsState') === false) {
            $content = preg_replace(
                '/(import com\.airbnb\.lottie\.compose\.\*)/',
                "$1\nimport androidx.compose.animation.core.animateFloatAsState\nimport androidx.compose.animation.core.FastOutSlowInEasing\nimport androidx.compose.ui.draw.alpha\nimport kotlinx.coroutines.delay",
                $content
            );
        }

        // Step 2: Load and populate the stub template
        $composeColor = $this->hexToComposeColor($backgroundColor);
        $alignment = match ($position) {
            'top' => 'Alignment.TopCenter',
            'bottom' => 'Alignment.BottomCenter',
            default => 'Alignment.Center',
        };

        // Clamp size between 0.1 and 1.0
        $size = max(0.1, min(1.0, $size));
        $sizeStr = number_format($size, 2, '.', '');

        $newSplashScreen = $this->loadStub('android/SplashAnimation.kt.stub');
        $newSplashScreen = $this->replaceStubVariables($newSplashScreen, [
            'ANIMATION_PATH' => $animationFilename,
            'BACKGROUND_COLOR' => $composeColor,
            'ALIGNMENT' => $alignment,
            'SIZE' => $sizeStr,
            'FADE_IN_DURATION' => $fadeInDuration,
        ]);

        // Step 3: Replace the SplashScreen composable
        // Match from @Composable annotation through to the closing brace at 4-space indent
        $pattern = '/@Composable\s+private fun SplashScreen\(\).*?^    \}/ms';
        $content = preg_replace($pattern, $newSplashScreen, $content, 1);

        if (file_put_contents($mainActivityPath, $content) === false) {
            $this->error("Failed to write modified MainActivity.kt");
            return;
        }

        $this->info('Successfully integrated Lottie animation into Android SplashScreen');
    }

    /**
     * Modify Android themes.xml to configure splash screen background color
     * This creates a seamless transition to the Lottie animation (similar to iOS LaunchScreen.storyboard)
     *
     * Modifies both base themes.xml and values-v31/themes.xml (Android 12+) if it exists,
     * since Android uses resource qualifiers and v31 takes priority on Android 12+ devices.
     */
    protected function modifyAndroidTheme(): void
    {
        // Get background color from config
        $backgroundColor = config('nativephp.loader.background_color', '#FFFFFF');
        $androidColor = $this->hexToAndroidColor($backgroundColor);

        // Modify both base themes and Android 12+ (v31) themes if they exist
        $themeFiles = [
            'values/themes.xml' => 'themes.xml.original',
            'values-v31/themes.xml' => 'themes-v31.xml.original',
        ];

        $modified = false;
        foreach ($themeFiles as $relativePath => $backupName) {
            $themesPath = $this->buildPath() . '/app/src/main/res/' . $relativePath;

            if (!file_exists($themesPath)) {
                continue;
            }

            if ($this->modifyThemeFile($themesPath, $backupName, $androidColor)) {
                $this->info("Successfully modified Android {$relativePath} for seamless splash transition");
                $modified = true;
            }
        }

        if (!$modified) {
            $this->warn("No themes.xml files found to modify");
        }
    }

    /**
     * Modify a single Android theme file with splash screen attributes
     */
    protected function modifyThemeFile(string $themesPath, string $backupName, string $androidColor): bool
    {
        // Backup original outside of res/ directory (Android build fails on non-.xml files in res/)
        $backupDir = $this->buildPath() . '/app/src/main/nativephp-backups';
        $this->ensureDirectory($backupDir);
        $originalPath = $backupDir . '/' . $backupName;

        if (!file_exists($originalPath)) {
            copy($themesPath, $originalPath);
        }

        // Always start from the original to allow config changes
        $content = file_get_contents($originalPath);

        // Add Android 12+ splash screen attributes before closing </style> tag
        $splashAttributes = <<<XML
        <!-- NativephpLoader: Splash screen configuration for seamless transition -->
        <item name="android:windowSplashScreenBackground">{$androidColor}</item>
        <item name="android:windowSplashScreenAnimatedIcon">@android:color/transparent</item>
        <item name="android:windowSplashScreenIconBackgroundColor">{$androidColor}</item>
    </style>
XML;

        // Replace the closing </style> tag with splash attributes + closing tag
        $content = preg_replace(
            '/<\/style>\s*<\/resources>/s',
            $splashAttributes . "\n</resources>",
            $content
        );

        if (file_put_contents($themesPath, $content) === false) {
            $this->error("Failed to write modified {$themesPath}");
            return false;
        }

        return true;
    }

    /**
     * Convert a hex color code to Android color resource format
     */
    protected function hexToAndroidColor(string $hex): string
    {
        // Remove # if present and ensure uppercase
        $hex = strtoupper(ltrim($hex, '#'));

        // Handle shorthand hex (e.g., #FFF)
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return '#' . $hex;
    }

    /**
     * Clear and recreate the iOS animations directory
     */
    protected function clearIosAnimationsDirectory(): void
    {
        $animationsDir = $this->buildPath() . '/NativePHP/Resources/animations';
        if (is_dir($animationsDir)) {
            $this->delete($animationsDir);
        }
        $this->ensureDirectory($animationsDir);
    }

    /**
     * Copy animation to iOS bundle
     * Returns the animation name (filename without extension) for Swift reference
     */
    protected function copyIosAnimation(string $sourcePath): ?string
    {
        // Clear animations directory first to ensure clean state
        $this->clearIosAnimationsDirectory();

        // Extract original filename without extension
        $animationName = pathinfo($sourcePath, PATHINFO_FILENAME);

        $destinationPath = $this->buildPath() . '/NativePHP/Resources/animations/' . $animationName . '.lottie';

        if (!copy($sourcePath, $destinationPath)) {
            $this->error("Failed to copy animation to: {$destinationPath}");
            return null;
        }

        $this->info("iOS animation copied to: {$destinationPath}");
        return $animationName;
    }

    /**
     * Modify SplashView.swift to integrate Lottie animation
     */
    protected function modifyIosSplashView(string $animationName): void
    {
        $splashViewPath = $this->buildPath() . '/NativePHP/SplashView.swift';

        if (!file_exists($splashViewPath)) {
            $this->warn("SplashView.swift not found at: {$splashViewPath}");
            return;
        }

        // Get config values
        $backgroundColor = config('nativephp.loader.background_color', '#FFFFFF');
        $size = (float) config('nativephp.loader.size', 0.8);
        $position = config('nativephp.loader.position', 'center');
        $fadeInDuration = (int) config('nativephp.loader.fade_in_duration', 600);

        // Debug output
        $this->info("Loader config - background: {$backgroundColor}, size: {$size}, position: {$position}, fade_in: {$fadeInDuration}ms");

        // Read the original SplashView from the NativePHP source (not the potentially modified one)
        $originalSplashViewPath = $this->buildPath() . '/NativePHP/SplashView.swift.original';

        // Backup original if not already done
        if (!file_exists($originalSplashViewPath)) {
            copy($splashViewPath, $originalSplashViewPath);
        }

        // Always start from the original to allow config changes
        $content = file_get_contents($originalSplashViewPath);

        // Inject Lottie code
        $modifiedContent = $this->injectLottieCode($content, $backgroundColor, $size, $position, $animationName, $fadeInDuration);

        if (file_put_contents($splashViewPath, $modifiedContent) === false) {
            $this->error("Failed to write modified SplashView.swift");
            return;
        }

        $this->info('Successfully integrated Lottie animation into SplashView');
    }

    /**
     * Modify LaunchScreen.storyboard to show configured background color
     * This creates a seamless transition to the Lottie animation
     */
    protected function modifyLaunchScreen(): void
    {
        $launchScreenPath = $this->buildPath() . '/NativePHP/LaunchScreen.storyboard';

        if (!file_exists($launchScreenPath)) {
            $this->warn("LaunchScreen.storyboard not found at: {$launchScreenPath}");
            return;
        }

        // Get background color from config
        $backgroundColor = config('nativephp.loader.background_color', '#FFFFFF');
        $storyboardColor = $this->hexToStoryboardColor($backgroundColor);

        // Backup original if not already done
        $originalPath = $launchScreenPath . '.original';
        if (!file_exists($originalPath)) {
            copy($launchScreenPath, $originalPath);
        }

        // Always start from the original to allow config changes
        $content = file_get_contents($originalPath);

        // Remove the imageView and just show background color
        $content = preg_replace(
            '/<imageView.*?<\/imageView>/s',
            '<!-- Modified by NativephpLoader - Image removed for seamless Lottie transition -->',
            $content
        );

        // Remove constraints that reference the removed image
        $content = preg_replace(
            '/<constraints>.*?<\/constraints>/s',
            '',
            $content
        );

        // Remove resources section with LaunchImage
        $content = preg_replace(
            '/\s*<resources>.*?<\/resources>/s',
            '',
            $content
        );

        // Update view background color
        $content = preg_replace(
            '/(<view[^>]*>)\s*(<rect[^>]*\/>)/s',
            "$1\n                $2\n                <color key=\"backgroundColor\" {$storyboardColor}/>",
            $content
        );

        if (file_put_contents($launchScreenPath, $content) === false) {
            $this->error("Failed to write modified LaunchScreen.storyboard");
            return;
        }

        $this->info('Successfully modified LaunchScreen.storyboard for seamless transition');
    }

    /**
     * Inject Lottie animation code into SplashView content
     */
    protected function injectLottieCode(string $content, string $backgroundColor, float $size, string $position, string $animationName, int $fadeInDuration): string
    {
        // Step 1: Add Lottie import after SwiftUI import
        $content = str_replace(
            "import SwiftUI",
            "import SwiftUI\nimport Lottie",
            $content
        );

        // Convert hex color to SwiftUI Color
        $swiftColor = $this->hexToSwiftColor($backgroundColor);

        // Determine alignment based on position
        $alignment = match ($position) {
            'top' => '.top',
            'bottom' => '.bottom',
            default => '.center',
        };

        // Clamp size between 0.1 and 1.0 and format for Swift
        $size = max(0.1, min(1.0, $size));
        $sizeStr = number_format($size, 2, '.', '');

        // Step 2: Add @State property for fade-in animation (before var body)
        if (strpos($content, 'animationOpacity') === false) {
            $content = preg_replace(
                '/(struct SplashView[^{]*\{)/',
                "$1\n    @State private var animationOpacity: Double = 0\n",
                $content
            );
        }

        // Step 3: Load and populate the stub template
        $fadeInSeconds = number_format($fadeInDuration / 1000, 2, '.', '');
        $newBody = $this->loadStub('ios/SplashAnimation.swift.stub');
        $newBody = $this->replaceStubVariables($newBody, [
            'ALIGNMENT' => $alignment,
            'SWIFT_COLOR' => $swiftColor,
            'ANIMATION_NAME' => $animationName,
            'SIZE' => $sizeStr,
            'FADE_IN_DURATION' => $fadeInSeconds,
        ]);

        // Replace the body using regex to match from "var body: some View {" to its closing brace
        $content = preg_replace(
            '/var body: some View \{.*?\n    \}/s',
            $newBody,
            $content,
            1
        );

        return $content;
    }

    /**
     * Convert a hex color code to SwiftUI Color
     */
    protected function hexToSwiftColor(string $hex): string
    {
        // Remove # if present
        $hex = ltrim($hex, '#');

        // Handle shorthand hex (e.g., #FFF)
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        // Parse RGB values
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        return sprintf('Color(red: %.3f, green: %.3f, blue: %.3f)', $r, $g, $b);
    }

    /**
     * Convert a hex color code to Storyboard color attributes
     */
    protected function hexToStoryboardColor(string $hex): string
    {
        // Remove # if present
        $hex = ltrim($hex, '#');

        // Handle shorthand hex (e.g., #FFF)
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        // Parse RGB values
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        return sprintf('red="%.6f" green="%.6f" blue="%.6f" alpha="1" colorSpace="custom" customColorSpace="sRGB"', $r, $g, $b);
    }

    /**
     * Convert a hex color code to Kotlin Compose Color
     */
    protected function hexToComposeColor(string $hex): string
    {
        // Remove # if present
        $hex = ltrim($hex, '#');

        // Handle shorthand hex (e.g., #FFF)
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        // Return Compose Color format with full alpha
        return sprintf('Color(0xFF%s)', strtoupper($hex));
    }

    /**
     * Get the path to a stub file.
     * Checks for a published (customized) stub first, falls back to bundled stub.
     */
    protected function getStubPath(string $stub): string
    {
        // Check for published stub first
        $publishedPath = resource_path("stubs/nativephp-loader/{$stub}");
        if (file_exists($publishedPath)) {
            return $publishedPath;
        }

        // Fall back to plugin bundled stub
        return $this->pluginPath() . "/resources/stubs/{$stub}";
    }

    /**
     * Load stub content from file.
     *
     * @throws \RuntimeException if stub file not found
     */
    protected function loadStub(string $stub): string
    {
        $path = $this->getStubPath($stub);
        if (!file_exists($path)) {
            throw new \RuntimeException("Stub file not found: {$stub}");
        }
        return file_get_contents($path);
    }

    /**
     * Replace placeholder variables in stub content.
     * Placeholders use the format {{ VARIABLE_NAME }} with flexible spacing.
     */
    protected function replaceStubVariables(string $stub, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $pattern = '/{{\s*' . preg_quote($key, '/') . '\s*}}/i';
            $stub = preg_replace($pattern, $value, $stub);
        }
        return $stub;
    }
}
