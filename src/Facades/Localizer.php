<?php

declare(strict_types=1);

namespace DevWizard\Localizer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string jsonPath(string $locale)
 * @method static string localePath(string $locale)
 * @method static string translationFilePath(string $locale, string $file)
 * @method static void create(string $locale, ?string $fromLocale = null)
 * @method static void rename(string $oldLocale, string $newLocale, ?string $fromLocale = null)
 * @method static array get(string $locale)
 * @method static array getJson(string $locale)
 * @method static array getAllPhpTranslations(string $locale)
 * @method static array getPhpTranslations(string $locale, string $file)
 * @method static void set(string $key, ?string $value = null, ?string $locale = null)
 * @method static void setPhp(string $key, mixed $value, ?string $locale = null)
 * @method static void bulkSet(array $items, ?string $locale = null)
 * @method static void bulkSetPhp(string $file, array $items, ?string $locale = null)
 * @method static void unset(string $key, ?string $locale = null)
 * @method static void unsetPhp(string $key, ?string $locale = null)
 * @method static void delete(string $locale)
 * @method static void translate(string $fromLocale, string $toLocale)
 * @method static array availableLocales()
 *
 * @see \DevWizard\Localizer\Localizer
 */
class Localizer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \DevWizard\Localizer\Localizer::class;
    }
}
