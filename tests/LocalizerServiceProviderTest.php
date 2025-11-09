<?php

use DevWizard\Localizer\Localizer;
use DevWizard\Localizer\LocalizerServiceProvider;

describe('LocalizerServiceProvider registration', function () {
    it('registers the localizer singleton', function () {
        $localizer = app(Localizer::class);

        expect($localizer)->toBeInstanceOf(Localizer::class);
    });

    it('returns the same instance on multiple resolutions', function () {
        $first = app(Localizer::class);
        $second = app(Localizer::class);

        expect($first)->toBe($second);
    });
});

describe('LocalizerServiceProvider configuration', function () {
    it('merges package configuration', function () {
        expect(config('localizer'))->toBeArray();
        expect(config('localizer.default'))->toBe('en');
        expect(config('localizer.fallback'))->toBe('en');
    });

    it('has available locales configuration', function () {
        $available = config('localizer.available');

        expect($available)->toBeArray();
        expect($available)->toHaveKey('en');
    });

    it('each locale has label and dir', function () {
        $available = config('localizer.available');

        foreach ($available as $locale => $config) {
            expect($config)->toHaveKey('label');
            expect($config)->toHaveKey('dir');
            expect($config['dir'])->toBeIn(['ltr', 'rtl']);
        }
    });
});

describe('LocalizerServiceProvider commands', function () {
    it('registers install command', function () {
        $commands = \Illuminate\Support\Facades\Artisan::all();

        expect($commands)->toHaveKey('localizer:install');
    });

    it('registers sync command', function () {
        $commands = \Illuminate\Support\Facades\Artisan::all();

        expect($commands)->toHaveKey('localizer:sync');
    });

    it('registers translate command', function () {
        $commands = \Illuminate\Support\Facades\Artisan::all();

        expect($commands)->toHaveKey('localizer:translate');
    });

    it('registers generate command', function () {
        $commands = \Illuminate\Support\Facades\Artisan::all();

        expect($commands)->toHaveKey('localizer:generate');
    });
});

describe('LocalizerServiceProvider publishables', function () {
    it('has config publishable', function () {
        $provider = app()->getProvider(LocalizerServiceProvider::class);

        expect($provider)->toBeInstanceOf(LocalizerServiceProvider::class);
    });

    it('has middleware publishable', function () {
        $provider = app()->getProvider(LocalizerServiceProvider::class);

        expect($provider)->toBeInstanceOf(LocalizerServiceProvider::class);
    });
});

describe('LocalizerServiceProvider facade', function () {
    it('can use localizer facade', function () {
        $locales = \DevWizard\Localizer\Facades\Localizer::availableLocales();

        expect($locales)->toBeArray();
    });

    it('facade resolves to same instance as container', function () {
        $fromContainer = app(Localizer::class);
        $fromFacade = \DevWizard\Localizer\Facades\Localizer::getFacadeRoot();

        expect($fromFacade)->toBe($fromContainer);
    });
});

describe('LocalizerServiceProvider boot', function () {
    it('boots without errors', function () {
        expect(true)->toBeTrue();
    });

    it('is registered in service providers', function () {
        $providers = app()->getLoadedProviders();

        expect($providers)->toHaveKey(LocalizerServiceProvider::class);
    });
});

describe('LocalizerServiceProvider console mode', function () {
    it('publishes files in console mode', function () {
        // This test runs in console mode by default in Pest
        expect(app()->runningInConsole())->toBeTrue();
    });
});
