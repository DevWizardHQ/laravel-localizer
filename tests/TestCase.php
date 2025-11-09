<?php

namespace DevWizard\Localizer\Tests;

use DevWizard\Localizer\LocalizerServiceProvider;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected string $tempLangPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary lang directory for testing
        $this->tempLangPath = sys_get_temp_dir().'/localizer-tests-'.uniqid();

        // Ensure parent directory exists
        if (! File::exists(dirname($this->tempLangPath))) {
            File::makeDirectory(dirname($this->tempLangPath), 0755, true);
        }

        File::makeDirectory($this->tempLangPath, 0755, true);

        // Set the config to use temp directory
        config()->set('localizer.path', $this->tempLangPath);

        // Clear any cached locale data and reset static properties
        app()->forgetInstance('localizer');

        // Reset Localizer static cache using reflection
        $this->resetLocalizerCache();
    }

    protected function tearDown(): void
    {
        // Clean up temporary lang directory
        if (File::exists($this->tempLangPath)) {
            File::deleteDirectory($this->tempLangPath);
        }

        parent::tearDown();
    }

    /**
     * Reset Localizer static caches using reflection
     */
    protected function resetLocalizerCache(): void
    {
        try {
            $reflection = new \ReflectionClass(\DevWizard\Localizer\Localizer::class);

            if ($reflection->hasProperty('cache')) {
                $cacheProperty = $reflection->getProperty('cache');
                $cacheProperty->setAccessible(true);
                $cacheProperty->setValue(null, []);
            }

            if ($reflection->hasProperty('langPath')) {
                $pathProperty = $reflection->getProperty('langPath');
                $pathProperty->setAccessible(true);
                $pathProperty->setValue(null, null);
            }

            if ($reflection->hasProperty('availableLocalesCache')) {
                $localesProperty = $reflection->getProperty('availableLocalesCache');
                $localesProperty->setAccessible(true);
                $localesProperty->setValue(null, null);
            }
        } catch (\ReflectionException $e) {
            // If reflection fails, continue anyway
        }
    }

    protected function getPackageProviders($app)
    {
        return [
            LocalizerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        // Setup default database to use sqlite :memory:
        tap($app['config'], function ($config) {
            $config->set('database.default', 'testing');

            // Set up localizer config for testing
            $config->set('localizer.default', 'en');
            $config->set('localizer.fallback', 'en');
            $config->set('localizer.available', [
                'en' => [
                    'label' => 'English',
                    'flag' => '🇬🇧',
                    'dir' => 'ltr',
                ],
                'es' => [
                    'label' => 'Spanish',
                    'flag' => '🇪🇸',
                    'dir' => 'ltr',
                ],
                'ar' => [
                    'label' => 'Arabic',
                    'flag' => '🇸🇦',
                    'dir' => 'rtl',
                ],
                'fr' => [
                    'label' => 'French',
                    'flag' => '��',
                    'dir' => 'ltr',
                ],
            ]);
        });
    }

    /**
     * Create a test locale with JSON and directory structure.
     */
    protected function createTestLocale(string $locale, array $jsonData = [], array $phpFiles = []): void
    {
        // Create JSON file
        $jsonPath = $this->tempLangPath.'/'.$locale.'.json';
        File::put($jsonPath, json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Create locale directory
        $localePath = $this->tempLangPath.'/'.$locale;
        if (! File::isDirectory($localePath)) {
            File::makeDirectory($localePath, 0755, true);
        }

        // Create PHP translation files
        foreach ($phpFiles as $fileName => $data) {
            $filePath = $localePath.'/'.$fileName.'.php';
            File::put($filePath, "<?php\n\nreturn ".var_export($data, true).";\n");
        }
    }

    /**
     * Assert that a JSON translation file exists with the given data.
     */
    protected function assertJsonTranslationExists(string $locale, ?array $expectedData = null): void
    {
        $jsonPath = $this->tempLangPath.'/'.$locale.'.json';

        expect(File::exists($jsonPath))->toBeTrue("JSON file for locale {$locale} does not exist");

        if ($expectedData !== null) {
            $actualData = json_decode(File::get($jsonPath), true);
            expect($actualData)->toBe($expectedData);
        }
    }

    /**
     * Assert that a PHP translation file exists with the given data.
     */
    protected function assertPhpTranslationExists(string $locale, string $file, ?array $expectedData = null): void
    {
        $filePath = $this->tempLangPath.'/'.$locale.'/'.$file.'.php';

        expect(File::exists($filePath))->toBeTrue("PHP file {$file}.php for locale {$locale} does not exist");

        if ($expectedData !== null) {
            $actualData = require $filePath;
            expect($actualData)->toBe($expectedData);
        }
    }

    /**
     * Assert that a locale directory exists.
     */
    protected function assertLocaleDirectoryExists(string $locale): void
    {
        $localePath = $this->tempLangPath.'/'.$locale;
        expect(File::isDirectory($localePath))->toBeTrue("Locale directory for {$locale} does not exist");
    }

    /**
     * Assert that a locale does not exist.
     */
    protected function assertLocaleDoesNotExist(string $locale): void
    {
        $jsonPath = $this->tempLangPath.'/'.$locale.'.json';
        $localePath = $this->tempLangPath.'/'.$locale;

        expect(File::exists($jsonPath))->toBeFalse("JSON file for locale {$locale} should not exist");
        expect(File::isDirectory($localePath))->toBeFalse("Locale directory for {$locale} should not exist");
    }
}
