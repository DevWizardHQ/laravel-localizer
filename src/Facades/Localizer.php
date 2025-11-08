<?php

namespace DevWizard\Localizer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \DevWizard\Localizer\Localizer
 */
class Localizer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \DevWizard\Localizer\Localizer::class;
    }
}
