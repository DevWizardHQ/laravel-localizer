<?php

declare(strict_types=1);

namespace DevWizard\Localizer\Jobs;

use DevWizard\Localizer\Facades\Localizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Sleep;
use Stichoza\GoogleTranslate\GoogleTranslate;

final class TranslateLang implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of translations to process before sleeping (rate limiting).
     */
    public int $tries = 10;

    /**
     * Seconds to sleep between batches (rate limiting).
     */
    public int $sleep = 1;

    /**
     * Job timeout in seconds (0 = no timeout).
     */
    public int $timeout = 0;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly string $fromLocale,
        private readonly string $toLocale
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Translate JSON translations
        $this->translateJson();

        // Translate PHP translations
        $this->translatePhp();
    }

    /**
     * Translate JSON translations.
     */
    private function translateJson(): void
    {
        $sourceTranslations = Localizer::getJson($this->fromLocale);
        $targetTranslations = Localizer::getJson($this->toLocale);
        $counter = 0;

        foreach ($sourceTranslations as $key => $value) {
            // Skip if already translated
            if (isset($targetTranslations[$key]) && ! empty($targetTranslations[$key])) {
                continue;
            }

            // Translate and set
            $translated = $this->translateText($value);
            Localizer::set($key, $translated, $this->toLocale);

            // Rate limiting: sleep after processing batch
            if (++$counter === $this->tries) {
                $counter = 0;
                Sleep::sleep($this->sleep);
            }
        }
    }

    /**
     * Translate PHP translations.
     */
    private function translatePhp(): void
    {
        $sourcePhpTranslations = Localizer::getAllPhpTranslations($this->fromLocale);
        $targetPhpTranslations = Localizer::getAllPhpTranslations($this->toLocale);
        $counter = 0;

        foreach ($sourcePhpTranslations as $file => $translations) {
            $targetFileTranslations = $targetPhpTranslations[$file] ?? [];
            $translatedData = $this->translateArray($translations, $targetFileTranslations, $counter);

            Localizer::bulkSetPhp($file, $translatedData, $this->toLocale);
        }
    }

    /**
     * Recursively translate an array of translations.
     */
    private function translateArray(array $source, array $target, int &$counter): array
    {
        $result = [];

        foreach ($source as $key => $value) {
            if (is_array($value)) {
                $targetValue = $target[$key] ?? [];
                $result[$key] = $this->translateArray($value, is_array($targetValue) ? $targetValue : [], $counter);
            } else {
                // Skip if already translated
                if (isset($target[$key]) && ! empty($target[$key])) {
                    $result[$key] = $target[$key];

                    continue;
                }

                // Translate
                $result[$key] = $this->translateText((string) $value);

                // Rate limiting: sleep after processing batch
                if (++$counter === $this->tries) {
                    $counter = 0;
                    Sleep::sleep($this->sleep);
                }
            }
        }

        return $result;
    }

    /**
     * Translate text using Google Translate.
     */
    private function translateText(?string $text): ?string
    {
        if (! $text) {
            return $text;
        }

        $translator = new GoogleTranslate;
        $translator->setSource($this->fromLocale === $this->toLocale ? null : $this->fromLocale);
        $translator->setTarget($this->toLocale);

        return $translator->translate($text);
    }
}
