<?php

declare(strict_types=1);

// config for DevWizard/Localizer
return [

    /*
    |--------------------------------------------------------------------------
    | Default Language
    |--------------------------------------------------------------------------
    |
    | This option controls the default language that will be used by the
    | translation service provider. You are free to set this value to any of
    | the languages supported by your application.
    |
    */

    'default' => env('APP_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Fallback Language
    |--------------------------------------------------------------------------
    |
    | The fallback language is used when the current language is not available.
    | You may change this value to any of the languages supported by your
    | application.
    |
    */

    'fallback' => env('APP_FALLBACK_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Available Languages
    |--------------------------------------------------------------------------
    |
    | This array contains all available languages for the application including
    | their label, flag emoji, and text direction (ltr/rtl) for display in
    | language dropdowns and UI components.
    |
    */

    'available' => [
        'en' => [
            'label' => 'English',
            'flag' => '🇬🇧',
            'dir' => 'ltr',
        ],
        'ar' => [
            'label' => 'Arabic',
            'flag' => '🇸🇦',
            'dir' => 'rtl',
        ],
        'bn' => [
            'label' => 'Bengali',
            'flag' => '🇧🇩',
            'dir' => 'ltr',
        ],
        'es' => [
            'label' => 'Spanish',
            'flag' => '🇪🇸',
            'dir' => 'ltr',
        ],
        'fr' => [
            'label' => 'French',
            'flag' => '🇫🇷',
            'dir' => 'ltr',
        ],
        'de' => [
            'label' => 'German',
            'flag' => '🇩🇪',
            'dir' => 'ltr',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Language Path
    |--------------------------------------------------------------------------
    |
    | The path to the lang folder where translation files are stored.
    |
    */

    'path' => lang_path(),

    /*
    |--------------------------------------------------------------------------
    | TypeScript Output Path
    |--------------------------------------------------------------------------
    |
    | The path where generated TypeScript translation files will be saved.
    | These files can be imported in your frontend (React/Vue/Inertia) app.
    |
    */

    'typescript_output_path' => resource_path('js/lang'),

    /*
    |--------------------------------------------------------------------------
    | Scan Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for scanning the application to collect translation keys.
    | Defines which directories to include/exclude and which file types to scan.
    |
    */

    'scan' => [
        'include' => [
            app_path(),
            resource_path(),
            base_path('routes'),
        ],
        'exclude' => [
            base_path('bootstrap'),
            lang_path(),
            public_path(),
            storage_path(),
            base_path('vendor'),
            base_path('node_modules'),
        ],
        'extensions' => [
            'php',
            'blade.php',
            'js',
            'jsx',
            'ts',
            'tsx',
            'vue',
        ],
    ],

];
