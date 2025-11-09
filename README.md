# Laravel Localizer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/devwizardhq/laravel-localizer.svg?style=flat-square)](https://packagist.org/packages/devwizardhq/laravel-localizer)
[![Total Downloads](https://img.shields.io/packagist/dt/devwizardhq/laravel-localizer.svg?style=flat-square)](https://packagist.org/packages/devwizardhq/laravel-localizer)

Seamlessly bridge Laravel translations to your SPA frontend (React/Vue) with automatic TypeScript generation, type-safe usage, and powerful localization features.

## Features

- 🔍 **Auto-scan translations** - Automatically discover translation keys from your codebase
- 🌐 **Auto-translate** - Use Google Translate to automatically translate missing keys
- 📦 **TypeScript generation** - Export translations as TypeScript files for frontend use
- 🎯 **Type-safe** - Full IDE support with PHPDoc annotations
- ⚡ **Performance** - In-memory caching and build-time generation
- 🔄 **Vendor support** - Automatically includes translations from vendor packages
- 🌍 **RTL support** - Built-in right-to-left language support
- 🎨 **Framework agnostic** - Works with React, Vue, and vanilla JavaScript

## Installation

Install via Composer:

```bash
composer require devwizardhq/laravel-localizer
```

Run the installation command:

```bash
php artisan localizer:install
```

This interactive installer will:
- ✅ Publish configuration file
- ✅ Create default locale files
- ✅ Publish and register middleware
- ✅ Setup TypeScript output directory
- ✅ Generate initial translation files

### Manual Setup

If you prefer manual configuration:

```bash
# Publish config
php artisan vendor:publish --tag="laravel-localizer-config"

# Publish middleware
php artisan vendor:publish --tag="laravel-localizer-middleware"
```

Register the middleware in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\LocalizerMiddleware::class,
    ]);
})
```

## Quick Start

### 1. Configure Available Locales

Edit `config/localizer.php`:

```php
return [
    'default' => 'en',
    'fallback' => 'en',
    
    'available' => [
        'en' => ['label' => 'English', 'flag' => '🇬🇧', 'dir' => 'ltr'],
        'fr' => ['label' => 'Français', 'flag' => '🇫🇷', 'dir' => 'ltr'],
        'ar' => ['label' => 'العربية', 'flag' => '🇸🇦', 'dir' => 'rtl'],
    ],
    
    'path' => lang_path(),
    'typescript_output_path' => resource_path('js/lang'),
];
```

### 2. Sync Translation Keys

Scan your codebase for translation keys:

```bash
php artisan localizer:sync --all
```

This finds all `__()`, `trans()`, and `lang()` calls and adds missing keys to your language files.

### 3. Generate TypeScript Files

Export translations for your frontend:

```bash
php artisan localizer:generate --all
```

Generates TypeScript files in `resources/js/lang/`:

```typescript
// resources/js/lang/en.ts
export const en = {
  "welcome": "Welcome",
  "validation.required": "This field is required"
} as const;
```

### 4. Use in Frontend

Install the corresponding frontend package:

```bash
# For React
npm install @devwizard/laravel-localizer-react

# For Vue
npm install @devwizard/laravel-localizer-vue
```

See the [React package README](https://www.npmjs.com/package/@devwizard/laravel-localizer-react) or [Vue package README](https://www.npmjs.com/package/@devwizard/laravel-localizer-vue) for frontend integration details.

## Usage

### Commands

#### Sync Translation Keys

```bash
# Sync all locales
php artisan localizer:sync --all

# Sync specific locales
php artisan localizer:sync --locales=en,fr

# Interactive selection
php artisan localizer:sync
```

#### Auto-translate

Automatically translate from one locale to another using Google Translate:

```bash
# With options
php artisan localizer:translate --source=en --target=fr

# Interactive selection
php artisan localizer:translate
```

**Note:** Requires `stichoza/google-translate-php`:

```bash
composer require stichoza/google-translate-php
```

#### Generate TypeScript Files

```bash
# Generate all locales
php artisan localizer:generate --all

# Generate specific locales
php artisan localizer:generate --locales=en,fr

# Interactive selection
php artisan localizer:generate
```

### Programmatic API

```php
use DevWizard\Localizer\Facades\Localizer;

// Create a new locale
Localizer::create('fr', fromLocale: 'en');

// Get all translations (JSON + PHP combined)
$translations = Localizer::get('en');

// Get JSON translations only
$jsonTranslations = Localizer::getJson('en');

// Set a JSON translation
Localizer::set('welcome', 'Welcome!', 'en');

// Set nested PHP translation
Localizer::setPhp('validation.required', 'Field is required', 'en');

// Bulk operations
Localizer::bulkSet([
    'hello' => 'Hello',
    'goodbye' => 'Goodbye',
], 'en');

// Check if translation exists
if (Localizer::has('welcome', 'en')) {
    // ...
}

// Get available locales
$locales = Localizer::availableLocales(); // ['en', 'fr', 'ar']

// Delete a locale
Localizer::delete('fr');

// Rename a locale
Localizer::rename('en', 'en-US');
```

### Middleware

The `LocalizerMiddleware` automatically:
- Detects user's preferred locale
- Sets the application locale
- Shares locale data with Inertia.js

**Locale Detection Priority:**

1. Query parameter: `?locale=fr`
2. Request header: `X-Locale: fr`
3. Session: stored from previous requests
4. User model: `$user->getLocale()` (if method exists)
5. Browser: `Accept-Language` header
6. Default: `config('localizer.default')`

**Shared Inertia Props:**

```javascript
{
  locale: {
    current: 'en',
    dir: 'ltr',
    available: {
      en: { label: 'English', flag: '🇬🇧', dir: 'ltr' },
      fr: { label: 'Français', flag: '🇫🇷', dir: 'ltr' },
      ar: { label: 'العربية', flag: '🇸🇦', dir: 'rtl' }
    }
  }
}
```

## Translation Organization

The package uses dot notation to organize translations:

- **JSON keys** (no dots): `welcome`, `hello world` → stored in `{locale}.json`
- **PHP keys** (with dots): `validation.required`, `auth.failed` → stored in `{locale}/{file}.php`

Example structure:

```
lang/
├── en.json                 # Simple translations
├── en/
│   ├── validation.php      # Validation messages
│   └── auth.php           # Auth messages
├── fr.json
└── fr/
    ├── validation.php
    └── auth.php
```

## Deployment

### Generated Files

The installer automatically adds generated TypeScript files to `.gitignore`. In your deployment process, regenerate them:

```bash
# After composer install
php artisan localizer:generate --all

# Then build frontend
npm run build
```

### Example CI/CD

```bash
#!/bin/bash

composer install --no-dev --optimize-autoloader
npm ci

# Generate translations before building
php artisan localizer:generate --all

npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Frontend Packages

- **React**: [@devwizard/laravel-localizer-react](https://www.npmjs.com/package/@devwizard/laravel-localizer-react)
- **Vue**: [@devwizard/laravel-localizer-vue](https://www.npmjs.com/package/@devwizard/laravel-localizer-vue)

Both packages provide:
- Hooks/composables for translations
- Vite plugin for automatic regeneration
- Full TypeScript support
- Inertia.js integration
- Placeholder replacement
- Pluralization support

## Configuration

The `config/localizer.php` file provides extensive customization options:

```php
return [
    // Default and fallback locales
    'default' => env('APP_LOCALE', 'en'),
    'fallback' => env('APP_FALLBACK_LOCALE', 'en'),
    
    // Available locales with metadata
    'available' => [
        'en' => ['label' => 'English', 'flag' => '🇬🇧', 'dir' => 'ltr'],
    ],
    
    // Paths
    'path' => lang_path(),
    'typescript_output_path' => resource_path('js/lang'),
    
    // Scanning configuration
    'scan' => [
        'include' => [app_path(), resource_path(), base_path('routes')],
        'exclude' => [base_path('vendor'), base_path('node_modules')],
        'extensions' => ['php', 'blade.php', 'js', 'jsx', 'ts', 'tsx', 'vue'],
    ],
];
```

## Testing

```bash
composer test
```


## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [IQBAL HASAN](https://github.com/iqbalhasandev)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
