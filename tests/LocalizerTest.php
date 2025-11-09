<?php

use DevWizard\Localizer\Facades\Localizer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

describe('Localizer paths', function () {
    it('can get json path for a locale', function () {
        $path = Localizer::jsonPath('en');
        expect($path)->toContain('/en.json');
    });

    it('can get locale directory path', function () {
        $path = Localizer::localePath('es');
        expect($path)->toContain('/es');
    });

    it('can get translation file path', function () {
        $path = Localizer::translationFilePath('en', 'messages');
        expect($path)->toContain('/en/messages.php');
    });
});

describe('Localizer create', function () {
    it('can create a new locale with json and directory', function () {
        Localizer::create('en');

        test()->assertJsonTranslationExists('en');
        test()->assertLocaleDirectoryExists('en');
    });

    it('can create a locale from an existing locale', function () {
        test()->createTestLocale('en', [
            'Hello' => 'Hello',
            'Goodbye' => 'Goodbye',
        ], [
            'messages' => ['welcome' => 'Welcome to our app'],
        ]);

        Localizer::create('es', 'en');

        test()->assertJsonTranslationExists('es', [
            'Hello' => 'Hello',
            'Goodbye' => 'Goodbye',
        ]);
        test()->assertPhpTranslationExists('es', 'messages', [
            'welcome' => 'Welcome to our app',
        ]);
    });

    it('invalidates available locales cache after creation', function () {
        $beforeCreate = Localizer::availableLocales();

        Localizer::create('fr');

        $afterCreate = Localizer::availableLocales();
        expect($afterCreate)->toContain('fr');
        expect($beforeCreate)->not->toContain('fr');
    });
});

describe('Localizer rename', function () {
    it('can rename a locale', function () {
        test()->createTestLocale('en', ['Hello' => 'Hello']);

        Localizer::rename('en', 'en-US');

        test()->assertLocaleDoesNotExist('en');
        test()->assertJsonTranslationExists('en-US', ['Hello' => 'Hello']);
    });

    it('can update a locale from another locale', function () {
        test()->createTestLocale('en', ['Hello' => 'Hello']);
        test()->createTestLocale('es', ['Hola' => 'Hola']);

        Localizer::rename('es', 'es-MX', 'en');

        test()->assertJsonTranslationExists('es-MX', ['Hello' => 'Hello']);
    });

    it('invalidates cache after renaming', function () {
        test()->createTestLocale('en', ['Hello' => 'Hello']);

        Localizer::rename('en', 'en-US');

        $locales = Localizer::availableLocales();
        expect($locales)->toContain('en-US');
        expect($locales)->not->toContain('en');
    });
});

describe('Localizer get', function () {
    it('can get all translations for a locale', function () {
        test()->createTestLocale('en', [
            'Hello' => 'Hello',
            'Goodbye' => 'Goodbye',
        ], [
            'messages' => ['welcome' => 'Welcome'],
        ]);

        $translations = Localizer::get('en');

        expect($translations)->toHaveKey('Hello', 'Hello');
        expect($translations)->toHaveKey('Goodbye', 'Goodbye');
        expect($translations)->toHaveKey('messages.welcome', 'Welcome');
    });

    it('caches translations after first load', function () {
        test()->createTestLocale('en', ['Hello' => 'Hello']);

        // First call - loads from file
        $first = Localizer::get('en');

        // Modify file directly
        $langPath = config('localizer.path');
        $jsonPath = $langPath.'/en.json';
        File::put($jsonPath, json_encode(['Modified' => 'Modified']));

        // Second call - should return cached version
        $second = Localizer::get('en');

        expect($second)->toBe($first);
        expect($second)->toHaveKey('Hello');
        expect($second)->not->toHaveKey('Modified');
    });
});

describe('Localizer getJson', function () {
    it('can get json translations only', function () {
        test()->createTestLocale('en', [
            'Hello' => 'Hello',
        ], [
            'messages' => ['welcome' => 'Welcome'],
        ]);

        $json = Localizer::getJson('en');

        expect($json)->toHaveKey('Hello', 'Hello');
        expect($json)->not->toHaveKey('messages.welcome');
    });

    it('creates json file if it does not exist', function () {
        $json = Localizer::getJson('new-locale');

        expect($json)->toBe([]);
        test()->assertJsonTranslationExists('new-locale');
    });
});

describe('Localizer getAllPhpTranslations', function () {
    it('can get all php translations', function () {
        test()->createTestLocale('en', [], [
            'messages' => ['welcome' => 'Welcome'],
            'validation' => ['required' => 'Required field'],
        ]);

        $phpTranslations = Localizer::getAllPhpTranslations('en');

        expect($phpTranslations)->toHaveKey('messages');
        expect($phpTranslations['messages'])->toBe(['welcome' => 'Welcome']);
        expect($phpTranslations)->toHaveKey('validation');
        expect($phpTranslations['validation'])->toBe(['required' => 'Required field']);
    });

    it('returns empty array if locale directory does not exist', function () {
        $phpTranslations = Localizer::getAllPhpTranslations('nonexistent');

        expect($phpTranslations)->toBe([]);
    });
});

describe('Localizer getPhpTranslations', function () {
    it('can get specific php translation file', function () {
        test()->createTestLocale('en', [], [
            'messages' => ['welcome' => 'Welcome', 'goodbye' => 'Goodbye'],
        ]);

        $messages = Localizer::getPhpTranslations('en', 'messages');

        expect($messages)->toBe(['welcome' => 'Welcome', 'goodbye' => 'Goodbye']);
    });

    it('returns empty array if file does not exist', function () {
        $messages = Localizer::getPhpTranslations('en', 'nonexistent');

        expect($messages)->toBe([]);
    });
});

describe('Localizer set', function () {
    it('can set a translation key in json', function () {
        test()->createTestLocale('en', []);

        Localizer::set('greeting', 'Hello World', 'en');

        $json = Localizer::getJson('en');
        expect($json)->toHaveKey('greeting');
        expect($json['greeting'])->toContain('Hello World');
    });

    it('uses current locale if not specified', function () {
        app()->setLocale('en');
        test()->createTestLocale('en', []);

        Localizer::set('greeting', 'Hello World');

        test()->assertJsonTranslationExists('en');
    });

    it('html encodes the value', function () {
        test()->createTestLocale('en', []);

        Localizer::set('test', '<script>alert("xss")</script>', 'en');

        $json = Localizer::getJson('en');
        expect($json['test'])->not->toContain('<script>');
        expect($json['test'])->toContain('&lt;script&gt;');
    });

    it('uses key as value if value is null', function () {
        test()->createTestLocale('en', []);

        Localizer::set('auto.key', null, 'en');

        $json = Localizer::getJson('en');
        expect($json)->toHaveKey('auto.key');
        expect($json['auto.key'])->toBe('auto.key');
    });
});

describe('Localizer setPhp', function () {
    it('can set a translation key in php file', function () {
        test()->createTestLocale('en', [], ['messages' => []]);

        Localizer::setPhp('messages.welcome', 'Welcome!', 'en');

        $messages = Localizer::getPhpTranslations('en', 'messages');
        expect($messages)->toHaveKey('welcome', 'Welcome!');
    });

    it('can set nested keys using dot notation', function () {
        test()->createTestLocale('en', [], ['messages' => []]);

        Localizer::setPhp('messages.auth.failed', 'Authentication failed', 'en');

        $messages = Localizer::getPhpTranslations('en', 'messages');
        expect($messages)->toHaveKey('auth.failed');
        expect($messages['auth']['failed'])->toBe('Authentication failed');
    });

    it('creates php file if it does not exist', function () {
        Localizer::setPhp('new.key', 'value', 'en');

        test()->assertPhpTranslationExists('en', 'new');
    });
});

describe('Localizer bulkSet', function () {
    it('can set multiple json translations at once', function () {
        test()->createTestLocale('en', ['existing' => 'value']);

        Localizer::bulkSet([
            'key1' => 'value1',
            'key2' => 'value2',
        ], 'en');

        $json = Localizer::getJson('en');
        expect($json)->toHaveKey('key1', 'value1');
        expect($json)->toHaveKey('key2', 'value2');
        expect($json)->toHaveKey('existing', 'value'); // Preserves existing
    });
});

describe('Localizer bulkSetPhp', function () {
    it('can set multiple php translations at once', function () {
        test()->createTestLocale('en', [], [
            'messages' => ['existing' => 'value'],
        ]);

        Localizer::bulkSetPhp('messages', [
            'key1' => 'value1',
            'key2' => 'value2',
        ], 'en');

        $messages = Localizer::getPhpTranslations('en', 'messages');
        expect($messages)->toHaveKey('key1', 'value1');
        expect($messages)->toHaveKey('key2', 'value2');
        expect($messages)->toHaveKey('existing', 'value');
    });

    it('can set nested arrays', function () {
        test()->createTestLocale('en', [], ['validation' => []]);

        Localizer::bulkSetPhp('validation', [
            'required' => 'Required',
            'email' => [
                'invalid' => 'Invalid email',
                'taken' => 'Email taken',
            ],
        ], 'en');

        $validation = Localizer::getPhpTranslations('en', 'validation');
        expect($validation['email']['invalid'])->toBe('Invalid email');
        expect($validation['email']['taken'])->toBe('Email taken');
    });
});

describe('Localizer unset', function () {
    it('can delete a key from json translations', function () {
        test()->createTestLocale('en', [
            'keep' => 'Keep this',
            'remove' => 'Remove this',
        ]);

        Localizer::unset('remove', 'en');

        $json = Localizer::getJson('en');
        expect($json)->toHaveKey('keep');
        expect($json)->not->toHaveKey('remove');
    });
});

describe('Localizer unsetPhp', function () {
    it('can delete a key from php translations', function () {
        test()->createTestLocale('en', [], [
            'messages' => [
                'keep' => 'Keep this',
                'remove' => 'Remove this',
            ],
        ]);

        Localizer::unsetPhp('messages.remove', 'en');

        $messages = Localizer::getPhpTranslations('en', 'messages');
        expect($messages)->toHaveKey('keep');
        expect($messages)->not->toHaveKey('remove');
    });

    it('can delete nested keys', function () {
        test()->createTestLocale('en', [], [
            'validation' => [
                'email' => [
                    'required' => 'Email required',
                    'invalid' => 'Email invalid',
                ],
            ],
        ]);

        Localizer::unsetPhp('validation.email.invalid', 'en');

        $validation = Localizer::getPhpTranslations('en', 'validation');
        expect($validation['email'])->toHaveKey('required');
        expect($validation['email'])->not->toHaveKey('invalid');
    });
});

describe('Localizer delete', function () {
    it('can delete a locale completely', function () {
        test()->createTestLocale('en', ['Hello' => 'Hello'], [
            'messages' => ['welcome' => 'Welcome'],
        ]);

        Localizer::delete('en');

        test()->assertLocaleDoesNotExist('en');
    });

    it('invalidates available locales cache after deletion', function () {
        test()->createTestLocale('en', ['Hello' => 'Hello']);

        Localizer::delete('en');

        $locales = Localizer::availableLocales();
        expect($locales)->not->toContain('en');
    });
});

describe('Localizer translate', function () {
    it('dispatches translation job', function () {
        // Skip this test since GoogleTranslate package is not installed in test environment
        expect(true)->toBeTrue();
    })->skip('GoogleTranslate package not installed in test environment');

    it('throws exception if google translate package is not installed', function () {
        // The GoogleTranslate package is not installed in test environment
        expect(fn () => Localizer::translate('en', 'es'))
            ->toThrow(Exception::class, 'The Stichoza\GoogleTranslate\GoogleTranslate package is not installed');
    });
});

describe('Localizer availableLocales', function () {
    it('returns list of available locales from json files', function () {
        test()->createTestLocale('en', []);
        test()->createTestLocale('es', []);
        test()->createTestLocale('fr', []);

        $locales = Localizer::availableLocales();

        expect($locales)->toContain('en');
        expect($locales)->toContain('es');
        expect($locales)->toContain('fr');
    });

    it('returns list of available locales from directories', function () {
        $langPath = config('localizer.path');
        File::makeDirectory($langPath.'/de', 0755, true);
        File::makeDirectory($langPath.'/it', 0755, true);

        $locales = Localizer::availableLocales();

        expect($locales)->toContain('de');
        expect($locales)->toContain('it');
    });

    it('returns unique locales from both json and directories', function () {
        test()->createTestLocale('en', []);
        // The directory is already created by createTestLocale,
        // so we're testing that the locale appears only once

        $locales = Localizer::availableLocales();

        // Count occurrences of 'en' - should only appear once even though
        // it exists as both JSON file and directory
        $enCount = count(array_filter($locales, fn ($locale) => $locale === 'en'));
        expect($enCount)->toBe(1);
    });

    it('caches available locales', function () {
        test()->createTestLocale('en', []);

        $first = Localizer::availableLocales();

        // Create another locale
        test()->createTestLocale('es', []);

        // Should return cached version
        $second = Localizer::availableLocales();

        expect($second)->toBe($first);
        expect($second)->not->toContain('es');
    });
});

describe('Localizer caching', function () {
    it('caches json translations per locale', function () {
        test()->createTestLocale('en', ['Hello' => 'Hello']);

        $first = Localizer::getJson('en');

        // Modify the file
        $langPath = config('localizer.path');
        File::put($langPath.'/en.json', json_encode(['Modified' => 'Modified']));

        $second = Localizer::getJson('en');

        expect($second)->toBe($first);
    });

    it('cache is cleared after setting a value', function () {
        test()->createTestLocale('en', ['Hello' => 'Hello']);

        $before = Localizer::getJson('en');

        Localizer::set('Goodbye', 'Goodbye', 'en');

        $after = Localizer::getJson('en');

        expect($after)->not->toBe($before);
        expect($after)->toHaveKey('Goodbye');
    });

    it('cache is cleared after deleting a key', function () {
        test()->createTestLocale('en', ['Hello' => 'Hello', 'Goodbye' => 'Goodbye']);

        $before = Localizer::getJson('en');

        Localizer::unset('Goodbye', 'en');

        $after = Localizer::getJson('en');

        expect($after)->not->toBe($before);
        expect($after)->not->toHaveKey('Goodbye');
    });
});
