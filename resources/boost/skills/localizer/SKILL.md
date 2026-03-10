---
name: localizer
description: Manage translations, scan codebase for missing keys, generate TypeScript translation files, auto-translate with Google, and detect locale per-request.
---

# Localizer — Translation Management & SPA Bridge

## When to use this skill

Activate this skill when:
- Adding or modifying user-facing text in Blade, React, or Vue
- Setting up multi-language support
- Creating locale switcher UI
- Generating TypeScript translations for an SPA
- Managing JSON or PHP translation files programmatically
- Detecting or switching the active locale

## Strict Rules

### Backend Translation Rules

1. **ALWAYS use `__()` for all user-facing strings** in Blade templates, controllers, notifications, and anywhere text is displayed to users:
   ```php
   // CORRECT
   return __('Order has been placed successfully.');
   session()->flash('message', __('Profile updated.'));

   // WRONG — hardcoded English, cannot be translated
   return 'Order has been placed successfully.';
   ```

2. **ALWAYS use `__()` in Blade templates**, never raw text:
   ```blade
   {{-- CORRECT --}}
   <h1>{{ __('Dashboard') }}</h1>
   <p>{{ __('Welcome back, :name', ['name' => $user->name]) }}</p>
   <button>{{ __('Save Changes') }}</button>

   {{-- WRONG — untranslatable --}}
   <h1>Dashboard</h1>
   <button>Save Changes</button>
   ```

3. **Use two key types based on convention**:
   - **JSON keys** for UI strings — the key IS the English text: `__('Welcome back')` → stored in `lang/en.json`
   - **PHP keys** for structured/grouped translations — dot-notation: `__('validation.required')` → stored in `lang/en/validation.php`

4. **Key type is determined by the first dot-segment**:
   ```php
   // PHP key — first segment "auth" is a valid identifier → stored in lang/en/auth.php
   __('auth.failed')
   __('validation.required')
   __('messages.order.placed')

   // JSON key — no dot, or first segment is not a simple identifier → stored in lang/en.json
   __('Welcome')
   __('Hello, :name')
   __('Order #:id has been shipped.')
   ```

5. **ALWAYS use placeholders with `:name` syntax** for dynamic values. Never concatenate:
   ```php
   // CORRECT
   __('Hello, :name! You have :count messages.', ['name' => $user->name, 'count' => $count])

   // WRONG — broken for all non-English languages
   __('Hello, ') . $user->name . __('! You have ') . $count . __(' messages.')
   ```

6. **Use `Localizer` facade for programmatic translation management**, not direct file manipulation:
   ```php
   use DevWizard\Localizer\Facades\Localizer;

   // CORRECT — set a translation
   Localizer::set('Welcome', 'Welcome to our platform', 'en');
   Localizer::setPhp('messages.greeting', 'Hello there', 'en');

   // CORRECT — bulk set
   Localizer::bulkSet([
       'Welcome' => 'Welcome',
       'Goodbye' => 'Goodbye',
   ], 'en');

   // CORRECT — bulk set to a PHP file
   Localizer::bulkSetPhp('messages', [
       'greeting' => 'Hello',
       'farewell' => 'Goodbye',
   ], 'en');

   // WRONG — directly writing to JSON/PHP files
   file_put_contents(lang_path('en.json'), json_encode([...]));
   ```

7. **ALWAYS run `localizer:sync --all` after adding new translatable strings** to ensure all locales get the new keys:
   ```bash
   php artisan localizer:sync --all
   ```

8. **ALWAYS run `localizer:generate --all` after modifying any translations** to regenerate TypeScript files for the frontend:
   ```bash
   php artisan localizer:generate --all
   ```

### Locale Detection Rules

9. **ALWAYS use the `LocalizerMiddleware`** for locale detection. Never manually call `App::setLocale()` in controllers:
   ```php
   // bootstrap/app.php
   ->withMiddleware(function (Middleware $middleware) {
       $middleware->web(append: [
           \App\Http\Middleware\LocalizerMiddleware::class,
       ]);
   })
   ```

10. **The middleware detects locale in this strict priority order** (first match wins):
    1. `?locale=fr` — URL query string
    2. `X-Locale: fr` — Request header (for API/SPA requests)
    3. `session('locale')` — Session value (persists across pages)
    4. `$user->getLocale()` — Authenticated user preference (if method exists)
    5. `Accept-Language` header — Browser preference
    6. `config('localizer.default')` — App default

11. **For per-user locale persistence**, implement `getLocale()` on the User model:
    ```php
    // In User model
    public function getLocale(): string
    {
        return $this->locale ?? config('localizer.default');
    }
    ```

12. **ALWAYS register new locales in the config** before using them:
    ```php
    // config/localizer.php
    'available' => [
        'en' => ['label' => 'English', 'flag' => '🇬🇧', 'dir' => 'ltr'],
        'ar' => ['label' => 'Arabic',  'flag' => '🇸🇦', 'dir' => 'rtl'],
        'ja' => ['label' => 'Japanese','flag' => '🇯🇵', 'dir' => 'ltr'],
    ],
    ```

13. **To programmatically create a new locale** with all existing keys pre-populated:
    ```php
    // Create 'ja' by copying all keys from 'en'
    Localizer::create('ja', fromLocale: 'en');
    ```

### Frontend Rules

14. **ALWAYS use the `__()` function from the localizer package** in React/Vue components. Never hardcode text:
    ```tsx
    // CORRECT — React
    import { useLocalizer } from '@devwizard/laravel-localizer-react';

    function MyComponent() {
        const { __ } = useLocalizer();
        return <h1>{__('Welcome')}</h1>;
    }

    // WRONG — hardcoded, untranslatable
    function MyComponent() {
        return <h1>Welcome</h1>;
    }
    ```

    ```vue
    <!-- CORRECT — Vue -->
    <script setup>
    import { useLocalizer } from '@devwizard/laravel-localizer-vue';
    const { __ } = useLocalizer();
    </script>
    <template>
        <h1>{{ __('Welcome') }}</h1>
    </template>

    <!-- WRONG -->
    <template>
        <h1>Welcome</h1>
    </template>
    ```

15. **ALWAYS use the same key in frontend `__()` as in backend `__()`**. The keys must match exactly:
    ```php
    // Backend Blade
    {{ __('Order placed successfully.') }}
    ```
    ```tsx
    // Frontend React — SAME key
    __('Order placed successfully.')
    ```

16. **For locale switching UI**, use `setLocale` and `availableLocales` from the hook:
    ```tsx
    // React
    const { __, locale, setLocale, availableLocales } = useLocalizer();

    <select value={locale} onChange={e => setLocale(e.target.value)}>
        {availableLocales.map(loc => (
            <option key={loc} value={loc}>{loc}</option>
        ))}
    </select>
    ```

    ```vue
    <!-- Vue -->
    <select v-model="locale" @change="setLocale(locale)">
        <option v-for="loc in availableLocales" :key="loc" :value="loc">
            {{ loc }}
        </option>
    </select>
    ```

17. **NEVER manually create or edit files in `resources/js/lang/`**. These are auto-generated by `localizer:generate` and will be overwritten.

18. **For Inertia.js apps**, the middleware automatically shares locale data. Access it via the page props:
    ```tsx
    // Available in all Inertia pages when middleware is active
    const { locale } = usePage().props;
    // locale = { current: 'en', dir: 'ltr', available: {...} }
    ```

## Complete Implementation Patterns

### Pattern: Add Multi-language Support to an Existing App

**Step 1: Install and configure**

```bash
composer require devwizardhq/laravel-localizer
php artisan localizer:install
```

**Step 2: Register middleware in `bootstrap/app.php`**

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\LocalizerMiddleware::class,
    ]);
})
```

**Step 3: Add `dir` attribute to HTML layout for RTL support**

```blade
<html lang="{{ app()->getLocale() }}" dir="{{ config('localizer.available.' . app()->getLocale() . '.dir', 'ltr') }}">
```

**Step 4: Scan existing code for translation keys**

```bash
php artisan localizer:sync --all
```

**Step 5: Auto-translate to target languages**

```bash
composer require stichoza/google-translate-php
php artisan localizer:translate --source=en --target=fr
php artisan localizer:translate --source=en --target=ar
```

**Step 6: Generate TypeScript files for SPA**

```bash
php artisan localizer:generate --all
```

### Pattern: Locale Switcher in Blade

```blade
<nav>
    @foreach(config('localizer.available') as $code => $locale)
        <a href="?locale={{ $code }}"
           class="{{ app()->getLocale() === $code ? 'font-bold' : '' }}">
            {{ $locale['flag'] }} {{ $locale['label'] }}
        </a>
    @endforeach
</nav>
```

### Pattern: API with Locale Header

```js
// Send X-Locale header from frontend API client
axios.defaults.headers.common['X-Locale'] = currentLocale;

// The middleware automatically detects it — no controller logic needed
```

## Facade API Reference

```php
use DevWizard\Localizer\Facades\Localizer;
```

| Method | Purpose |
|--------|---------|
| `Localizer::get('en')` | All translations (JSON + PHP merged) |
| `Localizer::getJson('en')` | JSON translations only |
| `Localizer::getPhpTranslations('en', 'auth')` | Single PHP file |
| `Localizer::getAllPhpTranslations('en')` | All PHP files |
| `Localizer::set('key', 'value', 'en')` | Set JSON key (HTML-encoded) |
| `Localizer::setPhp('file.key', 'value', 'en')` | Set PHP key (dot-notation) |
| `Localizer::bulkSet([...], 'en')` | Batch set JSON keys |
| `Localizer::bulkSetPhp('file', [...], 'en')` | Batch set PHP keys |
| `Localizer::unset('key', 'en')` | Remove JSON key |
| `Localizer::unsetPhp('file.key', 'en')` | Remove PHP key |
| `Localizer::create('ja')` | Create new locale (empty) |
| `Localizer::create('ja', fromLocale: 'en')` | Create locale copying from another |
| `Localizer::delete('ja')` | Delete locale and all its files |
| `Localizer::rename('old', 'new')` | Rename locale |
| `Localizer::translate('en', 'fr')` | Auto-translate (queued job) |
| `Localizer::availableLocales()` | List all locale codes |

## Configuration Reference (`config/localizer.php`)

```php
'default'  => env('APP_LOCALE', 'en'),
'fallback' => env('APP_FALLBACK_LOCALE', 'en'),
'available' => [
    'en' => ['label' => 'English', 'flag' => '🇬🇧', 'dir' => 'ltr'],
    // Add more locales here
],
'path' => lang_path(),
'typescript_output_path' => resource_path('js/lang'),
'scan' => [
    'include'    => [app_path(), resource_path(), base_path('routes')],
    'exclude'    => [base_path('bootstrap'), lang_path(), public_path(), storage_path(), base_path('vendor'), base_path('node_modules')],
    'extensions' => ['php', 'blade.php', 'js', 'jsx', 'ts', 'tsx', 'vue'],
],
```

## Artisan Commands

```bash
php artisan localizer:sync --all               # Scan code and add missing keys to all locales
php artisan localizer:sync --locales=en,fr      # Sync specific locales only
php artisan localizer:generate --all            # Generate TypeScript files for all locales
php artisan localizer:generate --locales=en     # Generate for specific locales
php artisan localizer:translate --source=en --target=fr  # Auto-translate (queued)
```

## Common Anti-Patterns to Avoid

| Anti-Pattern | Correct Pattern |
|---|---|
| `<h1>Welcome</h1>` in Blade | `<h1>{{ __('Welcome') }}</h1>` |
| `return 'Success';` in controller | `return __('Success');` |
| `'Hello, ' . $name` concatenation | `__('Hello, :name', ['name' => $name])` |
| `App::setLocale('fr')` in controller | Use `LocalizerMiddleware` — it handles everything |
| Manually editing `resources/js/lang/*.ts` | Run `php artisan localizer:generate --all` |
| Manually editing `lang/*.json` files | Use `Localizer::set()` or `Localizer::bulkSet()` |
| Using different keys in backend vs frontend | Use the EXACT same key string in `__()` everywhere |
| Forgetting to run `sync` after adding strings | ALWAYS run `localizer:sync --all` then `localizer:generate --all` |
| Not registering locale in config `available` | Middleware rejects unknown locales silently |
