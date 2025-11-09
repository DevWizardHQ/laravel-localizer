# Changelog

All notable changes to `laravel-localizer` will be documented in this file.

## v1.0.0 - 2025-11-09

### 🎉 Initial Stable Release

This is the first stable release of Laravel Localizer, a powerful localization package that bridges Laravel translations to your SPA frontend (React/Vue) with automatic TypeScript generation.

### ✨ Core Features

- **Translation Management**
  - Create, read, update, and delete locales
  - Support for both JSON and PHP translation files
  - Bulk operations for efficient translation management
  - Nested translation support with dot notation
  - HTML entity escaping for security
  - In-memory caching for optimal performance

- **Automatic Translation Scanning**
  - Scan codebase for `__()`, `trans()`, and `lang()` calls
  - Support for multiple file extensions (PHP, Blade, JS, JSX, TS, TSX, Vue)
  - Handles escaped quotes and special characters
  - Configurable include/exclude patterns
  - Preserves existing translations during sync

- **TypeScript Generation**
  - Generate TypeScript files from Laravel translations
  - Auto-generated index file for easy imports
  - Type-safe translation keys
  - Vendor package translation support
  - Proper escaping for special characters

- **Auto-translation**
  - Integrate with Google Translate for automatic translations
  - Queued job processing for background translation
  - Rate limiting to avoid API throttling
  - Skip already translated keys
  - Support for nested PHP translation arrays

### 🎨 Frontend Integration

- **React Package** - `@devwizard/laravel-localizer-react`
  - `useLocalizer()` hook
  - Vite plugin for automatic regeneration
  - Full TypeScript support
  - Inertia.js integration

- **Vue Package** - `@devwizard/laravel-localizer-vue`
  - `useLocalizer()` composable
  - Reactive locale and direction
  - Vite plugin for automatic regeneration
  - Full TypeScript support
  - Inertia.js integration

### 🛠️ Commands

- `localizer:install` - Interactive installation wizard
- `localizer:sync` - Scan and sync translation keys
- `localizer:translate` - Auto-translate between locales
- `localizer:generate` - Generate TypeScript files

### 🔧 Middleware

- **LocalizerMiddleware** - Automatic locale detection
  - Query parameter (`?locale=fr`)
  - Request header (`X-Locale`)
  - Session storage
  - User model method (`$user->getLocale()`)
  - Browser language (`Accept-Language`)
  - Fallback to default locale
  - Share locale data with Inertia.js

### 📦 Configuration

- Extensive configuration options
- Support for multiple locales
- Locale metadata (label, flag, direction)
- RTL language support
- Customizable paths and scan patterns

### 🧪 Testing

- Comprehensive test suite with 80+ test cases
- Unit tests for core functionality
- Command tests for all Artisan commands
- Middleware tests for locale detection
- Job tests for translation processing
- 100% coverage of critical paths

### 📚 Documentation

- Complete README with examples
- API documentation
- Deployment guidelines
- Frontend integration guides
- Configuration reference

### 🔒 Security

- HTML entity escaping
- Safe file operations
- Protected against XSS
- Validated locale codes

### ⚡ Performance

- In-memory caching
- Efficient file operations
- Build-time TypeScript generation
- Lazy loading support

### 📝 Requirements

- PHP 8.4+
- Laravel 11.0+ or 12.0+
- Composer

### 🔗 Dependencies

- `spatie/laravel-package-tools` - Package scaffolding
- `illuminate/contracts` - Laravel framework integration
- `stichoza/google-translate-php` - Auto-translation (optional)

