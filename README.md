# laravel-collect-translations

This package provides a command `./artisan translation:collect` 
to collect all your translation items from your source code.

## Installation
```
$ composer require monyxie/laravel-collect-translations
```

## Usage
```
$ php artisan translations:collect
```

## Credits
The function that finds the translation items from source
are take directly from the [barryvdh/laravel-translation-manager
](https://github.com/barryvdh/laravel-translation-manager) package with little modification.
