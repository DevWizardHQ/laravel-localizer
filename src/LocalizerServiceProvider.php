<?php

namespace DevWizard\Localizer;

use DevWizard\Localizer\Commands\LocalizerCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LocalizerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-localizer')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel_localizer_table')
            ->hasCommand(LocalizerCommand::class);
    }
}
