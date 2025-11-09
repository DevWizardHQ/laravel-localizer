<?php

declare(strict_types=1);

namespace DevWizard\Localizer\Commands;

use DevWizard\Localizer\Facades\Localizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\multiselect;

final class GenerateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'localizer:generate
                            {--locales=* : Specific locales to generate (comma-separated or multiple --locales options)}
                            {--all : Generate TypeScript files for all available locales}';

    /**
     * The console command description.
     */
    protected $description = 'Generate TypeScript translation files from Laravel language files';

    /**
     * Locales to generate.
     */
    private array $locales = [];

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

        $outputPath = $this->config['typescript_output_path'] ?? resource_path('js/lang');

        if (! File::isDirectory($outputPath)) {
            File::makeDirectory($outputPath, 0755, true);
            $this->components->task("Created output directory: <fg=cyan>{$outputPath}</>");
        }

        $this->newLine();
        $this->components->info('📝 Generating TypeScript files...');
        $this->newLine();

        foreach ($this->locales as $locale) {
            $this->generateTypeScriptFile($locale, $outputPath);
        }

        // Generate index.ts to auto-export all locales
        $this->generateIndexFile($outputPath);

        $this->displaySummary($outputPath);

        return self::SUCCESS;
    }

    /**
     * Display command header.
     */
    private function displayHeader(): void
    {
        $this->newLine();
        $this->line('  <fg=cyan>╔═══════════════════════════════════════╗</>');
        $this->line('  <fg=cyan>║</fg=cyan>  <fg=white;options=bold>Laravel Localizer - Generate</fg=white;options=bold>   <fg=cyan>║</>');
        $this->line('  <fg=cyan>╚═══════════════════════════════════════╝</>');
        $this->newLine();
    }

    /**
     * Get the locales to generate from options or prompt user.
     */
    private function getLocales(): void
    {
        if ($this->option('all')) {
            $this->locales = Localizer::availableLocales();
            $this->components->info('📦 Generating <fg=yellow>'.count($this->locales).'</> TypeScript files: <fg=cyan>'.implode('</>, <fg=cyan>', $this->locales).'</>');

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
            $this->components->info('📦 Generating <fg=yellow>'.count($this->locales).'</> TypeScript files: <fg=cyan>'.implode('</>, <fg=cyan>', $this->locales).'</>');

            return;
        }

        // Interactive multiselect prompt
        $availableLocales = Localizer::availableLocales();
        if (empty($availableLocales)) {
            $this->components->error('No locales found. Please create at least one locale first.');

            return;
        }

        $this->newLine();
        $selected = multiselect(
            label: 'Which locales would you like to generate?',
            options: $availableLocales,
            default: $availableLocales,
            hint: 'Use space to select, enter to confirm'
        );

        $this->locales = $selected;
        $this->newLine();
    }

    /**
     * Generate TypeScript file for a locale.
     */
    private function generateTypeScriptFile(string $locale, string $outputPath): void
    {
        // Get all translations (JSON + flattened PHP)
        $translations = Localizer::get($locale);

        // Also include vendor translations if configured
        $translations = $this->mergeVendorTranslations($locale, $translations);

        // Convert to TypeScript format
        $tsContent = $this->generateTypeScriptContent($locale, $translations);

        // Write to file
        $filePath = $outputPath.'/'.$locale.'.ts';
        File::put($filePath, $tsContent);

        $keyCount = count($translations);
        $relativePath = str_replace(base_path().'/', '', $filePath);
        $this->components->task("Generated <fg=cyan>{$relativePath}</> <fg=gray>({$keyCount} keys)</>");
    }

    /**
     * Merge vendor translations into the main translations array.
     */
    private function mergeVendorTranslations(string $locale, array $translations): array
    {
        $langPath = $this->config['path'] ?? lang_path();
        $vendorPath = $langPath.'/vendor';

        if (! File::isDirectory($vendorPath)) {
            return $translations;
        }

        // Scan vendor packages
        foreach (File::directories($vendorPath) as $packagePath) {
            $packageName = basename($packagePath);
            $packageLocalePath = $packagePath.'/'.$locale;

            if (! File::isDirectory($packageLocalePath)) {
                continue;
            }

            // Load PHP files from vendor package
            foreach (File::files($packageLocalePath) as $file) {
                if ($file->getExtension() === 'php') {
                    $fileName = $file->getFilenameWithoutExtension();
                    $vendorTranslations = require $file->getPathname();

                    // Flatten and prefix with vendor namespace
                    foreach ($this->flattenArray($vendorTranslations) as $key => $value) {
                        $fullKey = "{$packageName}::{$fileName}.{$key}";
                        // App translations take precedence
                        if (! isset($translations[$fullKey])) {
                            $translations[$fullKey] = $value;
                        }
                    }
                }
            }
        }

        return $translations;
    }

    /**
     * Flatten a nested array with dot notation.
     */
    private function flattenArray(array $array, string $prepend = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && ! empty($value)) {
                $results = array_merge($results, $this->flattenArray($value, $prepend.$key.'.'));
            } else {
                $results[$prepend.$key] = $value;
            }
        }

        return $results;
    }

    /**
     * Generate TypeScript file content using stub.
     */
    private function generateTypeScriptContent(string $locale, array $translations): string
    {
        $stub = File::get(__DIR__.'/../../stubs/locale.ts.stub');
        $jsonContent = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return str_replace(
            ['{{ locale }}', '{{ timestamp }}', '{{ count }}', '{{ translations }}'],
            [$locale, $this->getCurrentTimestamp(), $this->countTranslations($translations), $jsonContent],
            $stub
        );
    }

    /**
     * Generate index.ts file that exports all available locales using stub.
     */
    private function generateIndexFile(string $outputPath): void
    {
        $availableLocales = Localizer::availableLocales();

        // Generate imports (import default, then re-export)
        $imports = [];
        $exports = [];
        foreach ($availableLocales as $locale) {
            $imports[] = "import {$locale} from './{$locale}';";
            $exports[] = "export { {$locale} };";
        }

        // Generate translations object entries
        $registryEntries = [];
        foreach ($availableLocales as $locale) {
            $registryEntries[] = "  {$locale},";
        }

        $stub = File::get(__DIR__.'/../../stubs/index.ts.stub');

        $content = str_replace(
            ['{{ timestamp }}', '{{ locales }}', '{{ imports }}', '{{ exports }}', '{{ registry }}'],
            [
                $this->getCurrentTimestamp(),
                $this->formatLocaleList($availableLocales),
                implode("\n", $imports),
                implode("\n", $exports),
                implode("\n", $registryEntries),
            ],
            $stub
        );

        $filePath = $outputPath.'/index.ts';
        File::put($filePath, $content);

        $relativePath = str_replace(base_path().'/', '', $filePath);
        $this->components->task("Generated <fg=cyan>{$relativePath}</> <fg=gray>(registry)</>");
    }

    /**
     * Format locale list for display.
     */
    private function formatLocaleList(array $locales): string
    {
        return implode(', ', $locales);
    }

    /**
     * Count total translations recursively.
     */
    private function countTranslations(array $translations): int
    {
        $count = 0;
        foreach ($translations as $value) {
            if (is_array($value)) {
                $count += $this->countTranslations($value);
            } else {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get current timestamp.
     */
    private function getCurrentTimestamp(): string
    {
        return now()->toIso8601String();
    }

    /**
     * Display generation summary.
     */
    private function displaySummary(string $outputPath): void
    {
        $this->newLine();
        $this->line('  <fg=green>╔═══════════════════════════════════════╗</>');
        $this->line('  <fg=green>║</fg=green>   <fg=white;options=bold>✓ Generation Completed!</fg=white;options=bold>        <fg=green>║</>');
        $this->line('  <fg=green>╚═══════════════════════════════════════╝</>');
        $this->newLine();

        $this->components->twoColumnDetail('<fg=gray>Files generated:</>', '<fg=yellow>'.count($this->locales).'</>');
        $this->components->twoColumnDetail('<fg=gray>Output directory:</>', '<fg=cyan>'.str_replace(base_path().'/', '', $outputPath).'</>');

        $this->newLine();
        $this->components->info('✅ TypeScript translation files generated successfully!');
        $this->newLine();
    }
}
