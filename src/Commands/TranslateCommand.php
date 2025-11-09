<?php

declare(strict_types=1);

namespace DevWizard\Localizer\Commands;

use DevWizard\Localizer\Facades\Localizer;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

final class TranslateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'localizer:translate
                            {--source= : Source locale to translate from}
                            {--target= : Target locale to translate to}';

    /**
     * The console command description.
     */
    protected $description = 'Automatically translate language files from one locale to another using Google Translate';

    /**
     * Source locale to translate from.
     */
    private string $sourceLocale;

    /**
     * Target locale to translate to.
     */
    private string $targetLocale;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->displayHeader();

            $this->promptForLocales();

            $this->newLine();
            $this->components->info("🌐 Translating from <fg=cyan>{$this->sourceLocale}</> to <fg=cyan>{$this->targetLocale}</>...");
            $this->newLine();

            spin(
                fn () => Localizer::translate($this->sourceLocale, $this->targetLocale),
                '⏳ Dispatching translation job...'
            );

            $this->newLine();
            $this->displaySuccess();

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->newLine();
            $this->components->error('❌ Translation failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Display command header.
     */
    private function displayHeader(): void
    {
        $this->newLine();
        $this->line('  <fg=cyan>╔═══════════════════════════════════════╗</>');
        $this->line('  <fg=cyan>║</fg=cyan>  <fg=white;options=bold>Laravel Localizer - Translate</fg=white;options=bold>   <fg=cyan>║</>');
        $this->line('  <fg=cyan>╚═══════════════════════════════════════╝</>');
        $this->newLine();
    }

    /**
     * Display success message.
     */
    private function displaySuccess(): void
    {
        $this->line('  <fg=green>╔═══════════════════════════════════════╗</>');
        $this->line('  <fg=green>║</fg=green>   <fg=white;options=bold>✓ Translation Job Queued!</fg=white;options=bold>      <fg=green>║</>');
        $this->line('  <fg=green>╚═══════════════════════════════════════╝</>');
        $this->newLine();
        $this->components->info('✅ Translation job has been queued successfully!');
        $this->components->warn('⚡ Translations will be processed in the background.');
        $this->components->warn('💡 Make sure your queue worker is running:');
        $this->line('   <fg=gray>php artisan queue:work</>');
        $this->newLine();
    }

    /**
     * Prompt the user for source and target locales.
     */
    private function promptForLocales(): void
    {
        $availableLocales = config('localizer.available', []);
        $localeOptions = array_map(fn ($locale) => $locale['label'] ?? $locale['code'] ?? 'Unknown', $availableLocales);

        // Get source locale
        if ($this->option('source')) {
            $this->sourceLocale = (string) $this->option('source');
        } else {
            $this->newLine();
            $this->sourceLocale = (string) select(
                label: '📤 Select source locale (translate from)',
                options: $localeOptions,
                default: config('localizer.default', 'en'),
                hint: 'This is the locale you want to translate from'
            );
        }

        // Get target locale
        if ($this->option('target')) {
            $this->targetLocale = (string) $this->option('target');
        } else {
            $targetOptions = array_filter(
                $localeOptions,
                fn ($key) => $key !== $this->sourceLocale,
                ARRAY_FILTER_USE_KEY
            );

            if (empty($targetOptions)) {
                throw new InvalidArgumentException('No target locale available. You need at least 2 locales configured.');
            }

            $this->targetLocale = (string) select(
                label: '📥 Select target locale (translate to)',
                options: $targetOptions,
                hint: 'This is the locale you want to translate to'
            );
        }

        // Validate locales exist
        if (! isset($availableLocales[$this->sourceLocale])) {
            throw new InvalidArgumentException("Source locale '{$this->sourceLocale}' is not configured.");
        }

        if (! isset($availableLocales[$this->targetLocale])) {
            throw new InvalidArgumentException("Target locale '{$this->targetLocale}' is not configured.");
        }
    }
}
