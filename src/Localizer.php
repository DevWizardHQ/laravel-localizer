<?php

declare(strict_types=1);

namespace DevWizard\Localizer;

use DevWizard\Localizer\Jobs\TranslateLang;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use stdClass;
use Stichoza\GoogleTranslate\GoogleTranslate;

final class Localizer
{
    /**
     * Static in-memory cache for all localization data.
     */
    private static array $cache = [];

    /**
     * Static cached lang path to avoid repeated config calls.
     */
    private static ?string $langPath = null;

    /**
     * Static cache for available locales (refreshed on create/delete/rename).
     */
    private static ?array $availableLocalesCache = null;

    /**
     * Get the path to the locale's JSON file.
     */
    public function jsonPath(string $locale): string
    {
        return self::getLangPath().'/'.$locale.'.json';
    }

    /**
     * Get the path to the locale's directory for PHP translation files.
     */
    public function localePath(string $locale): string
    {
        return self::getLangPath().'/'.$locale;
    }

    /**
     * Get the path to a specific PHP translation file.
     */
    public function translationFilePath(string $locale, string $file): string
    {
        return $this->localePath($locale).'/'.$file.'.php';
    }

    /**
     * Create a locale with JSON and directory structure.
     */
    public function create(string $locale, ?string $fromLocale = null): void
    {
        // Create JSON file
        $jsonData = $fromLocale ? $this->getJson($fromLocale) : [];
        $this->writeJson($locale, $jsonData);

        // Create locale directory
        $localePath = $this->localePath($locale);
        if (! File::isDirectory($localePath)) {
            File::makeDirectory($localePath, 0755, true);
        }

        // Copy PHP files if source locale provided
        if ($fromLocale && File::isDirectory($this->localePath($fromLocale))) {
            $this->copyTranslationFiles($fromLocale, $locale);
        }

        // Invalidate available locales cache
        self::$availableLocalesCache = null;
    }

    /**
     * Rename or update a locale.
     */
    public function rename(string $oldLocale, string $newLocale, ?string $fromLocale = null): void
    {
        if ($fromLocale && $fromLocale !== $newLocale) {
            $this->delete($newLocale);
            $this->create($newLocale, $fromLocale);

            return;
        }

        $oldJsonPath = $this->jsonPath($oldLocale);
        $newJsonPath = $this->jsonPath($newLocale);
        $oldLocalePath = $this->localePath($oldLocale);
        $newLocalePath = $this->localePath($newLocale);

        // Rename JSON file
        if (File::exists($oldJsonPath)) {
            File::move($oldJsonPath, $newJsonPath);
        }

        // Rename locale directory
        if (File::isDirectory($oldLocalePath)) {
            File::move($oldLocalePath, $newLocalePath);
        }

        // Invalidate caches for both old and new
        $this->clearCache($oldLocale);
        $this->clearCache($newLocale);
        self::$availableLocalesCache = null;
    }

    /**
     * Get all translation data for a locale (JSON + all PHP files combined).
     */
    public function get(string $locale): array
    {
        $cacheKey = $this->getCacheKey($locale, 'all');

        if (! isset(self::$cache[$cacheKey])) {
            self::$cache[$cacheKey] = $this->loadAllTranslations($locale);
        }

        return self::$cache[$cacheKey];
    }

    /**
     * Get JSON translation data only.
     */
    public function getJson(string $locale): array
    {
        $cacheKey = $this->getCacheKey($locale, 'json');

        if (! isset(self::$cache[$cacheKey])) {
            self::$cache[$cacheKey] = $this->loadJson($locale);
        }

        return self::$cache[$cacheKey];
    }

    /**
     * Get all PHP translation files for a locale.
     */
    public function getAllPhpTranslations(string $locale): array
    {
        $cacheKey = $this->getCacheKey($locale, 'php:all');

        if (! isset(self::$cache[$cacheKey])) {
            self::$cache[$cacheKey] = $this->loadAllPhpTranslations($locale);
        }

        return self::$cache[$cacheKey];
    }

    /**
     * Get translations from a specific PHP file.
     */
    public function getPhpTranslations(string $locale, string $file): array
    {
        $cacheKey = $this->getCacheKey($locale, "php:{$file}");

        if (! isset(self::$cache[$cacheKey])) {
            self::$cache[$cacheKey] = $this->loadPhpFile($locale, $file);
        }

        return self::$cache[$cacheKey];
    }

    /**
     * Set a translation key in JSON file.
     */
    public function set(string $key, ?string $value = null, ?string $locale = null): void
    {
        $locale ??= app()->getLocale();
        $data = $this->getJson($locale);
        $data[$key] = htmlentities($value ?? $key, ENT_QUOTES, 'UTF-8');

        $this->writeJson($locale, $data);
        $this->clearCache($locale, phpAll: true);
    }

    /**
     * Set a translation key in a PHP file using dot notation.
     */
    public function setPhp(string $key, mixed $value, ?string $locale = null): void
    {
        $locale ??= app()->getLocale();
        [$file, $nestedKey] = $this->parsePhpKey($key);

        $data = $this->getPhpTranslations($locale, $file);
        Arr::set($data, $nestedKey, $value);

        $this->writePhpFile($this->translationFilePath($locale, $file), $data);
        $this->clearCache($locale, $file);
    }

    /**
     * Store multiple key-value pairs in JSON file.
     */
    public function bulkSet(array $items, ?string $locale = null): void
    {
        $locale ??= app()->getLocale();
        $data = array_replace_recursive($this->getJson($locale), $items);

        $this->writeJson($locale, $data);
        $this->clearCache($locale, phpAll: true);
    }

    /**
     * Store multiple translations in a PHP file.
     */
    public function bulkSetPhp(string $file, array $items, ?string $locale = null): void
    {
        $locale ??= app()->getLocale();
        $data = array_replace_recursive($this->getPhpTranslations($locale, $file), $items);

        $this->writePhpFile($this->translationFilePath($locale, $file), $data);
        $this->clearCache($locale, $file);
    }

    /**
     * Delete a key from JSON file.
     */
    public function unset(string $key, ?string $locale = null): void
    {
        $locale ??= app()->getLocale();
        $data = $this->getJson($locale);
        unset($data[$key]);

        $this->writeJson($locale, $data);
        $this->clearCache($locale, phpAll: true);
    }

    /**
     * Delete a key from a PHP translation file.
     */
    public function unsetPhp(string $key, ?string $locale = null): void
    {
        $locale ??= app()->getLocale();
        [$file, $nestedKey] = $this->parsePhpKey($key);

        $path = $this->translationFilePath($locale, $file);
        if (! File::exists($path)) {
            return;
        }

        $data = $this->getPhpTranslations($locale, $file);
        Arr::forget($data, $nestedKey);

        $this->writePhpFile($path, $data);
        $this->clearCache($locale, $file);
    }

    /**
     * Delete a locale and all its translation files.
     */
    public function delete(string $locale): void
    {
        // Delete JSON file
        $jsonPath = $this->jsonPath($locale);
        if (File::exists($jsonPath)) {
            File::delete($jsonPath);
        }

        // Delete locale directory
        $localePath = $this->localePath($locale);
        if (File::isDirectory($localePath)) {
            File::deleteDirectory($localePath);
        }

        $this->clearCache($locale);
        self::$availableLocalesCache = null;
    }

    /**
     * Automatically translate and store values from one locale to another.
     */
    public function translate(string $fromLocale, string $toLocale): void
    {
        throw_unless(
            class_exists(GoogleTranslate::class),
            Exception::class,
            'The Stichoza\GoogleTranslate\GoogleTranslate package is not installed. Please install it to use the translate functionality.'
        );

        dispatch(new TranslateLang($fromLocale, $toLocale));
    }

    /**
     * Get list of all available locales.
     */
    public function availableLocales(): array
    {
        if (self::$availableLocalesCache === null) {
            $langPath = self::getLangPath();
            $locales = [];

            // Pre-compute paths to avoid repeated string ops
            $jsonPattern = $langPath.'/*.json';
            $dirPattern = $langPath.'/*';

            // Get locales from JSON files
            foreach (File::glob($jsonPattern) as $file) {
                $locales[] = pathinfo($file, PATHINFO_FILENAME);
            }

            // Get locales from directories
            foreach (File::directories($dirPattern) as $directory) {
                $locales[] = basename($directory);
            }

            self::$availableLocalesCache = array_unique($locales);
        }

        return self::$availableLocalesCache;
    }

    /**
     * Get the cached language path.
     */
    private static function getLangPath(): string
    {
        return self::$langPath ??= config('localizer.path', lang_path());
    }

    /**
     * Load all translations for a locale (JSON + PHP combined).
     */
    private function loadAllTranslations(string $locale): array
    {
        $data = $this->loadJson($locale);
        $phpData = $this->loadAllPhpTranslations($locale);

        // Merge PHP translations with dot notation keys
        foreach ($phpData as $file => $translations) {
            foreach (Arr::dot($translations) as $key => $value) {
                $data["{$file}.{$key}"] = $value;
            }
        }

        return $data;
    }

    /**
     * Load JSON translation file.
     */
    private function loadJson(string $locale): array
    {
        $path = $this->jsonPath($locale);

        if (! File::exists($path)) {
            $this->writeJson($locale, []);

            return [];
        }

        return json_decode(File::get($path), true) ?? [];
    }

    /**
     * Load all PHP translation files for a locale.
     */
    private function loadAllPhpTranslations(string $locale): array
    {
        $localePath = $this->localePath($locale);

        if (! File::isDirectory($localePath)) {
            return [];
        }

        $translations = [];
        $files = File::files($localePath);

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $fileName = $file->getFilenameWithoutExtension();
                $translations[$fileName] = $this->loadPhpFile($locale, $fileName);
            }
        }

        return $translations;
    }

    /**
     * Load a specific PHP translation file.
     */
    private function loadPhpFile(string $locale, string $file): array
    {
        $path = $this->translationFilePath($locale, $file);

        if (! File::exists($path)) {
            return [];
        }

        return require $path;
    }

    /**
     * Write JSON translation file.
     */
    private function writeJson(string $locale, array $data): void
    {
        $path = $this->jsonPath($locale);
        $content = empty($data)
            ? json_encode(new stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        File::put($path, $content);
    }

    /**
     * Write a PHP translation file with optimized formatting.
     */
    private function writePhpFile(string $path, array $data): void
    {
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $export = $this->exportArray($data);

        // Use stub for PHP array file
        $stub = File::get(__DIR__.'/../stubs/php-array.stub');
        $content = str_replace('{{ content }}', $export, $stub);

        File::put($path, $content);
    }

    /**
     * Custom array exporter (optimized for readability and speed).
     */
    private function exportArray(array $array): string
    {
        if (empty($array)) {
            return '[]';
        }

        $parts = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $parts[] = var_export($key, true).' => '.$this->exportArray($value);
            } else {
                $parts[] = var_export($key, true).' => '.var_export($value, true);
            }
        }

        return '['.implode(', ', $parts).']';
    }

    /**
     * Parse a PHP translation key into file and nested key parts.
     */
    private function parsePhpKey(string $key): array
    {
        $parts = explode('.', $key, 2);
        $file = $parts[0];
        $nestedKey = $parts[1] ?? '';

        if (! $file) {
            throw new Exception("Invalid key format. Expected 'file[.key]' format.");
        }

        return [$file, $nestedKey];
    }

    /**
     * Generate a cache key for a locale and type.
     */
    private function getCacheKey(string $locale, string $type): string
    {
        return "locale:{$locale}:{$type}";
    }

    /**
     * Clear cache for a locale.
     */
    private function clearCache(string $locale, ?string $file = null, bool $phpAll = false): void
    {
        if ($file) {
            unset(
                self::$cache[$this->getCacheKey($locale, "php:{$file}")],
                self::$cache[$this->getCacheKey($locale, 'php:all')]
            );
        } elseif ($phpAll) {
            unset(self::$cache[$this->getCacheKey($locale, 'php:all')]);
        } else {
            unset(self::$cache[$this->getCacheKey($locale, 'json')]);
        }

        unset(self::$cache[$this->getCacheKey($locale, 'all')]);
    }

    /**
     * Copy translation files from one locale to another.
     */
    private function copyTranslationFiles(string $fromLocale, string $toLocale): void
    {
        $fromPath = $this->localePath($fromLocale);
        $toPath = $this->localePath($toLocale);

        if (! File::isDirectory($toPath)) {
            File::makeDirectory($toPath, 0755, true);
        }

        $files = File::files($fromPath);

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                File::copy($file->getPathname(), $toPath.'/'.$file->getFilename());
            }
        }
    }
}
