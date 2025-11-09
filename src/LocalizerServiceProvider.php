<?php

declare(strict_types=1);

namespace DevWizard\Localizer;

use DevWizard\Localizer\Commands\GenerateCommand;
use DevWizard\Localizer\Commands\InstallCommand;
use DevWizard\Localizer\Commands\SyncCommand;
use DevWizard\Localizer\Commands\TranslateCommand;
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
            ->hasCommands([
                InstallCommand::class,
                SyncCommand::class,
                TranslateCommand::class,
                GenerateCommand::class,
            ]);
    }

    public function packageBooted(): void
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__.'/../config/localizer.php', 'localizer');

        if ($this->app->runningInConsole()) {
            // Publish config file
            $this->publishes([
                __DIR__.'/../config/localizer.php' => config_path('localizer.php'),
            ], 'laravel-localizer-config');

            // Publish middleware
            $this->publishes([
                __DIR__.'/../stubs/LocalizerMiddleware.php.stub' => app_path('Http/Middleware/LocalizerMiddleware.php'),
            ], 'laravel-localizer-middleware');
        }
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Localizer::class, function () {
            return new Localizer;
        });
    }
}
