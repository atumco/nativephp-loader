# NativePHP Loader

A NativePHP Mobile plugin that provides beautiful Lottie animated loading screens for iOS and Android apps.

Give your users something delightful to look at while your app loads. This plugin replaces the default static splash screen with smooth, eye-catching Lottie animations that work seamlessly on both platforms.

Choose from the bundled animations or bring your own — just drop in a `.lottie` file and configure the colors to match your brand.

## Installation

```bash
composer require atum/nativephp-loader
```

## Publishing Assets

Publish the demo animations and config to your Laravel app:

```bash
# Publish everything (recommended)
php artisan vendor:publish --tag=nativephp-loader

# Or publish separately
php artisan vendor:publish --tag=nativephp-loader-config
php artisan vendor:publish --tag=nativephp-loader-animations
```


### Example Animations

This plugin includes demo animations:

| Demo | Meditation | Native |
|:---:|:---:|:---:|
| ![](.github/bricks.gif) | ![](.github/meditation.gif) | ![](.github/native.gif) |
| `demo.lottie` | `meditation.lottie` | `native.lottie` |
| `#23b9d6` | `#1E40AF` | `#1a2332` |



## Configuration

Configuration can be controlled via environment variables or by publishing the config file.



### Environment Variables

Example configurations:



```env
# Demo animation (teal theme)
LOADER_ANIMATION_PATH="resources/animations/demo.lottie"
LOADER_BACKGROUND_COLOR="#23b9d6"

# Meditation animation (blue theme)
LOADER_ANIMATION_PATH="resources/animations/meditation.lottie"
LOADER_BACKGROUND_COLOR="#1E40AF"

# Native animation (dark theme)
LOADER_ANIMATION_PATH="resources/animations/native.lottie"
LOADER_BACKGROUND_COLOR="#1a2332"

# Common settings
LOADER_SIZE="0.6"
LOADER_POSITION="center"
LOADER_FADE_IN_DURATION="900"
```



### Custom Animations

You can use your own Lottie animations by:

1. Placing your `.lottie` file in `resources/animations/`
2. Setting the path in your `.env`:

```env
LOADER_ANIMATION_PATH=resources/animations/my-custom-animation.lottie
```

## Demo Animations License

The demo animations included in this plugin (`demo.lottie`, `meditation.lottie`, `native.lottie`) are free animations from [LottieFiles](https://lottiefiles.com/free-animations) and are subject to the [LottieFiles License](https://lottiefiles.com/page/license).

## License

MIT
