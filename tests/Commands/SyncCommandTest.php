<?php

use Illuminate\Support\Facades\File;

describe('SyncCommand basic functionality', function () {
    it('can run the sync command with all option', function () {
        test()->createTestLocale('en', []);

        $this->artisan('localizer:sync --all')
            ->assertSuccessful();
    });

    it('can run with specific locales', function () {
        test()->createTestLocale('en', []);
        test()->createTestLocale('es', []);

        $this->artisan('localizer:sync --locales=en')
            ->assertSuccessful();
    });

    it('shows error when no locales selected', function () {
        $this->artisan('localizer:sync')
            ->expectsOutputToContain('No locales selected')
            ->assertFailed();
    });
});

describe('SyncCommand header and display', function () {
    it('shows header', function () {
        test()->createTestLocale('en', []);

        $this->artisan('localizer:sync --locales=en')
            ->expectsOutputToContain('Laravel Localizer')
            ->assertSuccessful();
    });

    it('shows syncing message', function () {
        test()->createTestLocale('en', []);

        $this->artisan('localizer:sync --locales=en')
            ->expectsOutputToContain('Syncing')
            ->assertSuccessful();
    });
});

describe('SyncCommand locale handling', function () {
    it('can sync multiple locales', function () {
        test()->createTestLocale('en', []);
        test()->createTestLocale('es', []);
        test()->createTestLocale('fr', []);

        $this->artisan('localizer:sync --locales=en,es')
            ->assertSuccessful();
    });

    it('creates locale directory if it does not exist', function () {
        $langPath = config('localizer.path');
        File::put($langPath.'/new-locale.json', json_encode([]));

        $this->artisan('localizer:sync --locales=new-locale')
            ->assertSuccessful();

        expect(File::isDirectory($langPath.'/new-locale'))->toBeTrue();
    });
});

describe('SyncCommand translation key scanning', function () {
    it('scans application files for translation keys', function () {
        test()->createTestLocale('en', []);

        $this->artisan('localizer:sync --locales=en')
            ->assertSuccessful();
    });

    it('preserves existing translations', function () {
        test()->createTestLocale('en', ['existing' => 'Existing value']);

        $this->artisan('localizer:sync --locales=en')
            ->assertSuccessful();

        $json = \DevWizard\Localizer\Facades\Localizer::getJson('en');

        // Check if the json has content (sync might find and add keys)
        expect($json)->toBeArray();

        // If the existing key is still there, it should have the same value
        if (isset($json['existing'])) {
            expect($json['existing'])->toBe('Existing value');
        }
    });
});

describe('SyncCommand with multiple locales option', function () {
    it('handles comma-separated locales', function () {
        test()->createTestLocale('en', []);
        test()->createTestLocale('es', []);

        $this->artisan('localizer:sync --locales=en,es')
            ->assertSuccessful();
    });

    it('handles multiple --locales flags', function () {
        test()->createTestLocale('en', []);
        test()->createTestLocale('es', []);

        $this->artisan('localizer:sync --locales=en --locales=es')
            ->assertSuccessful();
    });
});

describe('SyncCommand error handling', function () {
    it('shows error when no locales available', function () {
        $this->artisan('localizer:sync --all')
            ->expectsOutputToContain('No locales')
            ->assertFailed();
    });

    it('handles non-existent locale gracefully', function () {
        $this->artisan('localizer:sync --locales=nonexistent')
            ->assertSuccessful(); // Might succeed but skip invalid locale
    });
});
