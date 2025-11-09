<?php

use Illuminate\Support\Facades\File;

describe('GenerateCommand basic functionality', function () {
    it('requires locales to be specified', function () {
        $this->artisan('localizer:generate')
            ->expectsOutputToContain('No locales selected')
            ->assertFailed();
    });

    it('can generate for all locales', function () {
        test()->createTestLocale('en', ['Hello' => 'Hello']);
        test()->createTestLocale('es', ['Hola' => 'Hola']);

        $outputPath = base_path('resources/js/lang');

        $this->artisan('localizer:generate --all')
            ->assertSuccessful();

        // Check that index.ts was generated
        expect(File::exists($outputPath.'/index.ts'))->toBeTrue();

        // Clean up
        if (File::isDirectory($outputPath)) {
            File::deleteDirectory($outputPath);
        }
    });

    it('can generate for specific locales', function () {
        test()->createTestLocale('en', ['Hello' => 'Hello']);
        test()->createTestLocale('es', ['Hola' => 'Hola']);

        $outputPath = base_path('resources/js/lang');

        $this->artisan('localizer:generate --locales=en')
            ->assertSuccessful();

        expect(File::exists($outputPath.'/en.ts'))->toBeTrue();

        // Clean up
        if (File::isDirectory($outputPath)) {
            File::deleteDirectory($outputPath);
        }
    });
});

describe('GenerateCommand TypeScript generation', function () {
    it('generates TypeScript files with correct structure', function () {
        test()->createTestLocale('en', [
            'Hello' => 'Hello',
            'Goodbye' => 'Goodbye',
        ]);

        $outputPath = base_path('resources/js/lang');

        $this->artisan('localizer:generate --locales=en')
            ->assertSuccessful();

        $content = File::get($outputPath.'/en.ts');

        expect($content)->toContain('Hello');
        expect($content)->toContain('Goodbye');

        // Clean up
        if (File::isDirectory($outputPath)) {
            File::deleteDirectory($outputPath);
        }
    });

    it('includes PHP translations in TypeScript output', function () {
        test()->createTestLocale('en', [], [
            'messages' => ['welcome' => 'Welcome to our app'],
        ]);

        $outputPath = base_path('resources/js/lang');

        $this->artisan('localizer:generate --locales=en')
            ->assertSuccessful();

        $content = File::get($outputPath.'/en.ts');

        expect($content)->toContain('messages.welcome');
        expect($content)->toContain('Welcome to our app');

        // Clean up
        if (File::isDirectory($outputPath)) {
            File::deleteDirectory($outputPath);
        }
    });
});

describe('GenerateCommand index file generation', function () {
    it('generates index.ts with all locale exports', function () {
        test()->createTestLocale('en', ['Hello' => 'Hello']);
        test()->createTestLocale('es', ['Hola' => 'Hola']);

        $outputPath = base_path('resources/js/lang');

        $this->artisan('localizer:generate --all')
            ->assertSuccessful();

        $indexContent = File::get($outputPath.'/index.ts');

        expect($indexContent)->toContain('en');
        expect($indexContent)->toContain('es');

        // Clean up
        if (File::isDirectory($outputPath)) {
            File::deleteDirectory($outputPath);
        }
    });
});

describe('GenerateCommand output directory', function () {
    it('creates output directory if it does not exist', function () {
        test()->createTestLocale('en', ['Hello' => 'Hello']);

        $outputPath = base_path('resources/js/lang');

        // Ensure directory does not exist
        if (File::isDirectory($outputPath)) {
            File::deleteDirectory($outputPath);
        }

        $this->artisan('localizer:generate --locales=en')
            ->expectsOutputToContain('Created output directory')
            ->assertSuccessful();

        expect(File::isDirectory($outputPath))->toBeTrue();

        // Clean up
        if (File::isDirectory($outputPath)) {
            File::deleteDirectory($outputPath);
        }
    });
});

describe('GenerateCommand with multiple locales', function () {
    it('can handle comma-separated locales', function () {
        test()->createTestLocale('en', ['Hello' => 'Hello']);
        test()->createTestLocale('es', ['Hola' => 'Hola']);
        test()->createTestLocale('fr', ['Bonjour' => 'Bonjour']);

        $outputPath = base_path('resources/js/lang');

        $this->artisan('localizer:generate --locales=en,es')
            ->assertSuccessful();

        expect(File::exists($outputPath.'/en.ts'))->toBeTrue();
        expect(File::exists($outputPath.'/es.ts'))->toBeTrue();
        expect(File::exists($outputPath.'/fr.ts'))->toBeFalse();

        // Clean up
        if (File::isDirectory($outputPath)) {
            File::deleteDirectory($outputPath);
        }
    });

    it('can handle multiple --locales options', function () {
        test()->createTestLocale('en', ['Hello' => 'Hello']);
        test()->createTestLocale('es', ['Hola' => 'Hola']);

        $outputPath = base_path('resources/js/lang');

        $this->artisan('localizer:generate --locales=en --locales=es')
            ->assertSuccessful();

        expect(File::exists($outputPath.'/en.ts'))->toBeTrue();
        expect(File::exists($outputPath.'/es.ts'))->toBeTrue();

        // Clean up
        if (File::isDirectory($outputPath)) {
            File::deleteDirectory($outputPath);
        }
    });
});

describe('GenerateCommand error handling', function () {
    it('shows error when no locales are available', function () {
        // Don't create any locales
        $this->artisan('localizer:generate --all')
            ->expectsOutputToContain('No locales')
            ->assertFailed(); // Command should fail when no locales available
    });

    it('handles invalid locale gracefully', function () {
        $outputPath = base_path('resources/js/lang');

        $this->artisan('localizer:generate --locales=invalid')
            ->assertSuccessful(); // Command might succeed but skip invalid locale

        // Clean up
        if (File::isDirectory($outputPath)) {
            File::deleteDirectory($outputPath);
        }
    });
});

describe('GenerateCommand display', function () {
    it('shows header', function () {
        test()->createTestLocale('en', ['Hello' => 'Hello']);

        $outputPath = base_path('resources/js/lang');

        $this->artisan('localizer:generate --locales=en')
            ->expectsOutputToContain('Laravel Localizer')
            ->assertSuccessful();

        // Clean up
        if (File::isDirectory($outputPath)) {
            File::deleteDirectory($outputPath);
        }
    });

    it('shows generation progress', function () {
        test()->createTestLocale('en', ['Hello' => 'Hello']);

        $outputPath = base_path('resources/js/lang');

        $this->artisan('localizer:generate --locales=en')
            ->expectsOutputToContain('Generating TypeScript files')
            ->assertSuccessful();

        // Clean up
        if (File::isDirectory($outputPath)) {
            File::deleteDirectory($outputPath);
        }
    });
});
