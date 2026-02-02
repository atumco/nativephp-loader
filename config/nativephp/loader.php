<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Animation Path
    |--------------------------------------------------------------------------
    |
    | The path to your Lottie animation file (.lottie or .json format).
    | This can be a path relative to your Laravel base_path().
    |
    | Bundled animations (publish first with vendor:publish):
    | - 'resources/animations/demo.lottie' - A simple loading animation
    | - 'resources/animations/meditation.lottie' - A calming meditation animation
    | - 'resources/animations/native.lottie' - NativePHP branded animation
    |
    | You can also provide your own custom .lottie animation file.
    |
    | Note: The bundled demo animations are free animations from LottieFiles
    | (https://lottiefiles.com/free-animations) and are subject to the
    | LottieFiles License (https://lottiefiles.com/page/license).
    |
    */

    'animation_path' => env('LOADER_ANIMATION_PATH', 'resources/animations/demo.lottie'),

    /*
    |--------------------------------------------------------------------------
    | Background Color
    |--------------------------------------------------------------------------
    |
    | The background color of the splash screen. Accepts hex color codes.
    | This should match your Lottie animation background for a seamless look.
    |
    | Examples: '#FFFFFF' (white), '#000000' (black), '#1E40AF' (blue)
    |
    */

    'background_color' => env('LOADER_BACKGROUND_COLOR', '#FFFFFF'),

    /*
    |--------------------------------------------------------------------------
    | Animation Size
    |--------------------------------------------------------------------------
    |
    | The size of the Lottie animation relative to the screen width.
    | Value should be between 0.1 (10%) and 1.0 (100%).
    |
    | Default: 0.8 (80% of screen width)
    |
    */

    'size' => env('LOADER_SIZE', 0.8),

    /*
    |--------------------------------------------------------------------------
    | Animation Position
    |--------------------------------------------------------------------------
    |
    | The vertical position of the Lottie animation on the screen.
    |
    | Options: 'center', 'top', 'bottom'
    |
    */

    'position' => env('LOADER_POSITION', 'center'),

    /*
    |--------------------------------------------------------------------------
    | Fade-In Duration
    |--------------------------------------------------------------------------
    |
    | The duration of the fade-in animation when the splash screen appears.
    | Value is in milliseconds.
    |
    | Default: 600 (0.6 seconds)
    |
    */

    'fade_in_duration' => env('LOADER_FADE_IN_DURATION', 600),

];
