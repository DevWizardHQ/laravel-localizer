<?php

declare(strict_types=1);

namespace DevWizard\Localizer\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

use function array_keys;

final class LocalizerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->determineLocale($request);

        // Set the application locale
        App::setLocale($locale);

        // Store in session for future requests
        Session::put('locale', $locale);

        // Get current locale config
        $availableLocales = config('localizer.available', []);
        $currentLocaleConfig = $availableLocales[$locale] ?? ['dir' => 'ltr'];

        // Share the locale with Inertia props (if Inertia is available)
        if (class_exists(Inertia::class)) {
            Inertia::share('locale', [
                'current' => $locale,
                'dir' => $currentLocaleConfig['dir'] ?? 'ltr',
                'available' => $availableLocales,
            ]);
        }

        return $next($request);
    }

    /**
     * Determine the appropriate locale for the request.
     */
    private function determineLocale(Request $request): string
    {
        // 1. Check URL query parameter (e.g., ?locale=fr)
        $queryLocale = $request->query('locale');
        if ($queryLocale && $this->isValidLocale($queryLocale)) {
            return $queryLocale;
        }

        // 2. Check request header (e.g., X-Locale: fr)
        $headerLocale = $request->header('X-Locale');
        if ($headerLocale && $this->isValidLocale($headerLocale)) {
            return $headerLocale;
        }

        // 3. Check session
        $sessionLocale = Session::get('locale');
        if ($sessionLocale && $this->isValidLocale($sessionLocale)) {
            return $sessionLocale;
        }

        // 4. Check user preference (if authenticated)
        if ($request->user() && method_exists($request->user(), 'getLocale')) {
            $userLocale = $request->user()->getLocale();
            if ($userLocale && $this->isValidLocale($userLocale)) {
                return $userLocale;
            }
        }

        // 5. Check browser accept-language header
        $preferredLocale = $request->getPreferredLanguage(
            array_keys(config('localizer.available', []))
        );
        if ($preferredLocale && $this->isValidLocale($preferredLocale)) {
            return $preferredLocale;
        }

        // 6. Fall back to default locale
        return config('localizer.default', config('app.locale', 'en'));
    }

    /**
     * Check if the locale is valid.
     */
    private function isValidLocale(string $locale): bool
    {
        $availableLocales = array_keys(config('localizer.available', []));

        return in_array($locale, $availableLocales, true);
    }
}
