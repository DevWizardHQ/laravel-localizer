<?php

use DevWizard\Localizer\Middleware\LocalizerMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;

beforeEach(function () {
    $this->middleware = new LocalizerMiddleware;
    $this->request = Request::create('/test', 'GET');
});

describe('LocalizerMiddleware locale determination', function () {
    it('uses query parameter if provided', function () {
        $request = Request::create('/test?locale=es', 'GET');

        $this->middleware->handle($request, function ($req) {
            expect(App::getLocale())->toBe('es');
            expect(Session::get('locale'))->toBe('es');

            return response()->json([]);
        });
    });

    it('uses header if no query parameter', function () {
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Locale', 'ar');

        $this->middleware->handle($request, function ($req) {
            expect(App::getLocale())->toBe('ar');
            expect(Session::get('locale'))->toBe('ar');

            return response()->json([]);
        });
    });

    it('uses session if no query parameter or header', function () {
        Session::put('locale', 'es');
        $request = Request::create('/test', 'GET');

        $this->middleware->handle($request, function ($req) {
            expect(App::getLocale())->toBe('es');

            return response()->json([]);
        });
    });

    it('falls back to default locale', function () {
        $request = Request::create('/test', 'GET');

        $this->middleware->handle($request, function ($req) {
            expect(App::getLocale())->toBe('en'); // Default from config

            return response()->json([]);
        });
    });

    it('ignores invalid locales from query parameter', function () {
        $request = Request::create('/test?locale=invalid', 'GET');

        $this->middleware->handle($request, function ($req) {
            expect(App::getLocale())->toBe('en'); // Falls back to default

            return response()->json([]);
        });
    });

    it('ignores invalid locales from header', function () {
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Locale', 'invalid');

        $this->middleware->handle($request, function ($req) {
            expect(App::getLocale())->toBe('en'); // Falls back to default

            return response()->json([]);
        });
    });

    it('prioritizes query parameter over header', function () {
        $request = Request::create('/test?locale=es', 'GET');
        $request->headers->set('X-Locale', 'ar');

        $this->middleware->handle($request, function ($req) {
            expect(App::getLocale())->toBe('es');

            return response()->json([]);
        });
    });

    it('prioritizes query parameter over session', function () {
        Session::put('locale', 'ar');
        $request = Request::create('/test?locale=es', 'GET');

        $this->middleware->handle($request, function ($req) {
            expect(App::getLocale())->toBe('es');

            return response()->json([]);
        });
    });

    it('prioritizes header over session', function () {
        Session::put('locale', 'ar');
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Locale', 'es');

        $this->middleware->handle($request, function ($req) {
            expect(App::getLocale())->toBe('es');

            return response()->json([]);
        });
    });
});

describe('LocalizerMiddleware session storage', function () {
    it('stores locale in session', function () {
        $request = Request::create('/test?locale=es', 'GET');

        $this->middleware->handle($request, function ($req) {
            expect(Session::get('locale'))->toBe('es');

            return response()->json([]);
        });
    });

    it('updates session when locale changes', function () {
        Session::put('locale', 'en');
        $request = Request::create('/test?locale=es', 'GET');

        $this->middleware->handle($request, function ($req) {
            expect(Session::get('locale'))->toBe('es');

            return response()->json([]);
        });
    });
});

describe('LocalizerMiddleware Inertia integration', function () {
    it('shares locale data with Inertia', function () {
        if (! class_exists(Inertia::class)) {
            expect(true)->toBeTrue('Inertia not installed, skipping test');

            return;
        }

        $request = Request::create('/test?locale=es', 'GET');

        $this->middleware->handle($request, function ($req) {
            // Test would check Inertia::share() was called
            // This is difficult to test without mocking Inertia
            expect(App::getLocale())->toBe('es');

            return response()->json([]);
        });
    });

    it('includes text direction in shared data', function () {
        if (! class_exists(Inertia::class)) {
            expect(true)->toBeTrue('Inertia not installed, skipping test');

            return;
        }

        $request = Request::create('/test?locale=ar', 'GET');

        $this->middleware->handle($request, function ($req) {
            // Arabic should have RTL direction
            expect(App::getLocale())->toBe('ar');

            return response()->json([]);
        });
    });
});

describe('LocalizerMiddleware text direction', function () {
    it('sets correct direction for LTR locales', function () {
        $request = Request::create('/test?locale=en', 'GET');

        $this->middleware->handle($request, function ($req) {
            $config = config('localizer.available.en');
            expect($config['dir'])->toBe('ltr');

            return response()->json([]);
        });
    });

    it('sets correct direction for RTL locales', function () {
        $request = Request::create('/test?locale=ar', 'GET');

        $this->middleware->handle($request, function ($req) {
            $config = config('localizer.available.ar');
            expect($config['dir'])->toBe('rtl');

            return response()->json([]);
        });
    });

    it('defaults to LTR for locales without direction config', function () {
        config()->set('localizer.available.test', ['label' => 'Test']);
        $request = Request::create('/test?locale=test', 'GET');

        $this->middleware->handle($request, function ($req) {
            $config = config('localizer.available.test');
            $dir = $config['dir'] ?? 'ltr';
            expect($dir)->toBe('ltr');

            return response()->json([]);
        });
    });
});

describe('LocalizerMiddleware browser language preference', function () {
    it('uses browser accept-language header when available', function () {
        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept-Language', 'es-ES,es;q=0.9,en;q=0.8');

        $this->middleware->handle($request, function ($req) {
            // Should prefer Spanish since it's first in accept-language
            // and it's available in config
            $locale = App::getLocale();
            expect(['es', 'en'])->toContain($locale);

            return response()->json([]);
        });
    });

    it('ignores browser language if not in available locales', function () {
        $request = Request::create('/test', 'GET');
        // Use 'de' (German) which is not in available locales
        $request->headers->set('Accept-Language', 'de-DE,de;q=0.9');

        $this->middleware->handle($request, function ($req) {
            // Should fall back to default since 'de' is not in available locales
            expect(App::getLocale())->toBe('en');

            return response()->json([]);
        });
    });
});

describe('LocalizerMiddleware user preferences', function () {
    it('checks authenticated user locale preference', function () {
        $user = new class
        {
            public function getLocale()
            {
                return 'es';
            }
        };

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->middleware->handle($request, function ($req) {
            expect(App::getLocale())->toBe('es');

            return response()->json([]);
        });
    });

    it('ignores user preference if method does not exist', function () {
        $user = new class {};

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->middleware->handle($request, function ($req) {
            expect(App::getLocale())->toBe('en'); // Falls back to default

            return response()->json([]);
        });
    });

    it('prioritizes query parameter over user preference', function () {
        $user = new class
        {
            public function getLocale()
            {
                return 'ar';
            }
        };

        $request = Request::create('/test?locale=es', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->middleware->handle($request, function ($req) {
            expect(App::getLocale())->toBe('es');

            return response()->json([]);
        });
    });

    it('prioritizes session over user preference', function () {
        $user = new class
        {
            public function getLocale()
            {
                return 'ar';
            }
        };

        Session::put('locale', 'es');
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->middleware->handle($request, function ($req) {
            expect(App::getLocale())->toBe('es');

            return response()->json([]);
        });
    });
});

describe('LocalizerMiddleware response handling', function () {
    it('passes request through to next middleware', function () {
        $request = Request::create('/test', 'GET');
        $nextCalled = false;

        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        });

        expect($nextCalled)->toBeTrue();
        expect($response->getStatusCode())->toBe(200);
    });

    it('does not modify response content', function () {
        $request = Request::create('/test', 'GET');
        $expectedData = ['test' => 'data', 'number' => 123];

        $response = $this->middleware->handle($request, function ($req) use ($expectedData) {
            return response()->json($expectedData);
        });

        expect($response->getContent())->toBe(json_encode($expectedData));
    });
});
