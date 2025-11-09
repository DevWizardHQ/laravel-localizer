<?php

declare(strict_types=1);

namespace DevWizard\Localizer\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

use function Laravel\Prompts\multiselect;

final class SyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'localizer:sync
                            {--locales=* : Specific locales to sync (comma-separated or multiple --locales options)}
                            {--all : Sync all available locales}';

    /**
     * The console command description.
     */
    protected $description = 'Scan the application for translation keys and sync them to language files';

    /**
     * Locales to sync.
     */
    private array $locales = [];

    /**
     * Translation data for each locale.
     */
    private array $translations = [];

    /**
     * Configuration array.
     */
    private array $config = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->config = config('localizer');

        $this->displayHeader();

        $this->getLocales();

        if (empty($this->locales)) {
            $this->components->error('No locales selected. Aborting.');

            return self::FAILURE;
        }

        $this->loadTranslations();
        $this->scanForTranslationKeys();
        $this->saveTranslations();

        $this->displaySummary();

        return self::SUCCESS;
    }

    /**
     * Display command header.
     */
    private function displayHeader(): void
    {
        $this->newLine();
        $this->line('  <fg=cyan>╔═══════════════════════════════════════╗</>');
        $this->line('  <fg=cyan>║</fg=cyan>   <fg=white;options=bold>Laravel Localizer - Sync</fg=white;options=bold>      <fg=cyan>║</>');
        $this->line('  <fg=cyan>╚═══════════════════════════════════════╝</>');
        $this->newLine();
    }

    /**
     * Get the locales to sync from options or prompt user.
     */
    private function getLocales(): void
    {
        if ($this->option('all')) {
            $this->locales = $this->getAvailableLocales();
            $this->components->info('📦 Syncing <fg=yellow>'.count($this->locales).'</> locales: <fg=cyan>'.implode('</>, <fg=cyan>', $this->locales).'</>');

            return;
        }

        $localesOption = $this->option('locales');
        if (! empty($localesOption)) {
            // Handle comma-separated and multiple --locales options
            $this->locales = [];
            foreach ($localesOption as $locale) {
                $this->locales = array_merge($this->locales, explode(',', $locale));
            }
            $this->locales = array_unique(array_map('trim', $this->locales));
            $this->components->info('📦 Syncing <fg=yellow>'.count($this->locales).'</> locales: <fg=cyan>'.implode('</>, <fg=cyan>', $this->locales).'</>');

            return;
        }

        // Interactive multiselect prompt
        $availableLocales = $this->getAvailableLocales();
        if (empty($availableLocales)) {
            $this->components->error('No locales found. Please create at least one locale first.');

            return;
        }

        $this->newLine();
        $selected = multiselect(
            label: 'Which locales would you like to sync?',
            options: $availableLocales,
            default: $availableLocales,
            hint: 'Use space to select, enter to confirm'
        );

        $this->locales = $selected;
        $this->newLine();
    }

    /**
     * Get list of available locales from lang directory.
     */
    private function getAvailableLocales(): array
    {
        $langPath = $this->config['path'] ?? lang_path();
        $locales = [];

        // Get locales from JSON files
        foreach (File::glob($langPath.'/*.json') as $file) {
            $locales[] = pathinfo($file, PATHINFO_FILENAME);
        }

        // Get locales from directories
        foreach (File::directories($langPath) as $directory) {
            $locales[] = basename($directory);
        }

        return array_unique($locales);
    }

    /**
     * Load existing translations for selected locales.
     */
    private function loadTranslations(): void
    {
        foreach ($this->locales as $locale) {
            $this->translations[$locale] = [
                'json' => [],
                'php' => [],
            ];

            $this->loadJsonTranslations($locale);
            $this->loadPhpTranslations($locale);
        }
    }

    /**
     * Load JSON translations for a locale.
     */
    private function loadJsonTranslations(string $locale): void
    {
        $jsonPath = ($this->config['path'] ?? lang_path()).'/'.$locale.'.json';

        if (File::exists($jsonPath)) {
            $this->translations[$locale]['json'] = json_decode(File::get($jsonPath), true) ?? [];
        }
    }

    /**
     * Load PHP translations for a locale.
     */
    private function loadPhpTranslations(string $locale): void
    {
        $localePath = ($this->config['path'] ?? lang_path()).'/'.$locale;

        if (! File::isDirectory($localePath)) {
            File::makeDirectory($localePath, 0755, true);
        }

        $files = File::files($localePath);
        $totalKeys = 0;

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $fileName = $file->getFilenameWithoutExtension();
                $this->translations[$locale]['php'][$fileName] = require $file->getPathname();
                $totalKeys += count(Arr::dot($this->translations[$locale]['php'][$fileName]));
            }
        }
    }

    /**
     * Scan application files for translation keys.
     */
    private function scanForTranslationKeys(): void
    {
        $files = $this->getFilesToScan();
        $totalFiles = count($files);
        $foundKeysCount = 0;
        $progressBar = $this->output->createProgressBar($totalFiles);

        $this->newLine();
        $this->components->info("🔎 Scanning <fg=yellow>{$totalFiles}</> files for translation keys...");
        $this->newLine();
        $progressBar->start();

        foreach ($files as $file) {
            $content = File::get($file->getPathname());

            // Scan for JSON translation keys: __('welcome'), trans('welcome'), lang('welcome')
            $jsonKeys = $this->extractJsonKeys($content);
            foreach ($jsonKeys as $key) {
                $this->addJsonKey($key);
                $foundKeysCount++;
            }

            // Scan for PHP file translation keys: __('validation.required'), trans('validation.required')
            $phpKeys = $this->extractPhpKeys($content);
            foreach ($phpKeys as $key) {
                $this->addPhpKey($key);
                $foundKeysCount++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);
    }

    /**
     * Extract JSON translation keys from content.
     */
    private function extractJsonKeys(string $content): array
    {
        $keys = [];

        // Single quotes - handle escaped quotes with \'
        preg_match_all("/(?:__|trans|lang)\\s*\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/su", $content, $singleMatches);
        if (! empty($singleMatches[1])) {
            foreach ($singleMatches[1] as $match) {
                // Unescape the matched string
                $unescaped = stripcslashes($match);
                if (! $this->hasNamespaceSeparator($unescaped)) {
                    $keys[] = $unescaped;
                }
            }
        }

        // Double quotes - handle escaped quotes with \"
        preg_match_all('/(?:__|trans|lang)\\s*\\(\\s*"((?:[^"\\\\]|\\\\.)*)"/su', $content, $doubleMatches);
        if (! empty($doubleMatches[1])) {
            foreach ($doubleMatches[1] as $match) {
                // Unescape the matched string
                $unescaped = stripcslashes($match);
                if (! $this->hasNamespaceSeparator($unescaped)) {
                    $keys[] = $unescaped;
                }
            }
        }

        return array_unique($keys);
    }

    /**
     * Extract PHP file translation keys from content.
     */
    private function extractPhpKeys(string $content): array
    {
        $keys = [];

        // Single quotes - handle escaped quotes with \'
        preg_match_all("/(?:__|trans|lang)\\s*\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/su", $content, $singleMatches);
        if (! empty($singleMatches[1])) {
            foreach ($singleMatches[1] as $match) {
                // Unescape the matched string
                $unescaped = stripcslashes($match);
                if ($this->hasNamespaceSeparator($unescaped)) {
                    $keys[] = $unescaped;
                }
            }
        }

        // Double quotes - handle escaped quotes with \"
        preg_match_all('/(?:__|trans|lang)\\s*\\(\\s*"((?:[^"\\\\]|\\\\.)*)"/su', $content, $doubleMatches);
        if (! empty($doubleMatches[1])) {
            foreach ($doubleMatches[1] as $match) {
                // Unescape the matched string
                $unescaped = stripcslashes($match);
                if ($this->hasNamespaceSeparator($unescaped)) {
                    $keys[] = $unescaped;
                }
            }
        }

        return array_unique($keys);
    }

    /**
     * Check if a string has a namespace separator (e.g., 'auth.failed' vs 'Hello. World.')
     */
    private function hasNamespaceSeparator(string $key): bool
    {
        // Must contain at least one dot
        if (mb_strpos($key, '.') === false) {
            return false;
        }

        // Split by dots and check if we have valid namespace parts
        $parts = explode('.', $key);

        // Need at least 2 parts
        if (count($parts) < 2) {
            return false;
        }

        // Check if first part looks like a namespace (alphanumeric, underscore, dash)
        $firstPart = mb_trim($parts[0]);
        if (empty($firstPart) || ! preg_match('/^[a-zA-Z0-9_-]+$/', $firstPart)) {
            return false;
        }

        // Check if second part exists and isn't just whitespace
        $secondPart = mb_trim($parts[1]);
        if (empty($secondPart)) {
            return false;
        }

        return true;
    }

    /**
     * Add a JSON translation key to all locales.
     */
    private function addJsonKey(string $key): void
    {
        foreach ($this->locales as $locale) {
            if (! array_key_exists($key, $this->translations[$locale]['json'])) {
                $this->translations[$locale]['json'][$key] = $key;
            }
        }
    }

    /**
     * Add a PHP file translation key to all locales.
     */
    private function addPhpKey(string $key): void
    {
        // Parse key like 'messages.welcome' into file='messages' and path='welcome'
        $parts = explode('.', $key, 2);

        if (count($parts) < 2) {
            return;
        }

        [$file, $path] = $parts;

        foreach ($this->locales as $locale) {
            if (! isset($this->translations[$locale]['php'][$file])) {
                $this->translations[$locale]['php'][$file] = [];
            }

            // Use Arr::has to check nested keys
            if (! Arr::has($this->translations[$locale]['php'][$file], $path)) {
                Arr::set($this->translations[$locale]['php'][$file], $path, $path);
            }
        }
    }

    /**
     * Save all translations back to files.
     */
    private function saveTranslations(): void
    {
        foreach ($this->locales as $locale) {
            $this->saveJsonTranslations($locale);
            $this->savePhpTranslations($locale);
        }
    }

    /**
     * Save JSON translations for a locale.
     */
    private function saveJsonTranslations(string $locale): void
    {
        $path = ($this->config['path'] ?? lang_path()).'/'.$locale.'.json';
        $content = json_encode($this->translations[$locale]['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        File::put($path, $content);
    }

    /**
     * Save PHP translation files for a locale.
     */
    private function savePhpTranslations(string $locale): void
    {
        $localePath = ($this->config['path'] ?? lang_path()).'/'.$locale;
        $fileCount = 0;
        $totalKeys = 0;

        foreach ($this->translations[$locale]['php'] ?? [] as $file => $translations) {
            $filePath = $localePath.'/'.$file.'.php';
            $export = var_export($translations, true);

            // Use stub for PHP array file
            $stub = File::get(__DIR__.'/../../stubs/php-array.stub');
            $content = str_replace('{{ content }}', $export, $stub);

            File::put($filePath, $content);
            $fileCount++;
            $totalKeys += count(Arr::dot($translations));
        }
    }

    /**
     * Display sync summary.
     */
    private function displaySummary(): void
    {
        $this->newLine();
        $this->line('  <fg=green>╔═══════════════════════════════════════╗</>');
        $this->line('  <fg=green>║</fg=green>   <fg=white;options=bold>✓ Sync Completed Successfully</fg=white;options=bold>    <fg=green>║</>');
        $this->line('  <fg=green>╚═══════════════════════════════════════╝</>');
        $this->newLine();

        $this->components->twoColumnDetail('<fg=gray>Locales synced:</>', '<fg=yellow>'.count($this->locales).'</>');

        foreach ($this->locales as $locale) {
            $jsonCount = count($this->translations[$locale]['json'] ?? []);
            $phpCount = 0;
            foreach ($this->translations[$locale]['php'] ?? [] as $translations) {
                $phpCount += count(Arr::dot($translations));
            }
            $total = $jsonCount + $phpCount;

            $this->components->twoColumnDetail(
                "  <fg=cyan>{$locale}</>",
                "<fg=yellow>{$total}</> keys (<fg=gray>{$jsonCount} JSON, {$phpCount} PHP</>)"
            );
        }

        $this->newLine();
    }

    /**
     * Get the files to scan for translation keys.
     */
    private function getFilesToScan(): Finder
    {
        $finder = new Finder;
        $includes = $this->config['scan']['include'] ?? [];
        $excludes = $this->config['scan']['exclude'] ?? [];
        $extensions = $this->config['scan']['extensions'] ?? [];

        if (empty($includes)) {
            $this->components->error('No include paths configured in config/localizer.php');

            return $finder;
        }

        $finder->files()->in($includes);

        foreach ($excludes as $exclude) {
            $finder->notPath($exclude);
        }

        foreach ($extensions as $extension) {
            $finder->name('*.'.$extension);
        }

        return $finder;
    }
}
