<?php

declare(strict_types=1);

namespace DevWizard\Localizer\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use stdClass;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

final class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'localizer:install
                            {--framework= : Frontend framework (react, vue)}
                            {--force : Overwrite existing files}';

    /**
     * The console command description.
     */
    protected $description = 'Install Laravel Localizer and set up frontend integration';

    /**
     * Configuration array.
     */
    private array $config = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->config = config('localizer');

        $this->components->info('Installing Laravel Localizer...');
        $this->newLine();

        // Step 1: Publish configuration
        $this->publishConfig();

        // Step 2: Create default locale files
        $this->createDefaultLocales();

        // Step 3: Publish middleware
        $this->publishMiddleware();

        // Step 4: Install frontend package
        $this->installFrontendPackage();

        // Step 5: Setup TypeScript output directory
        $this->setupTypeScriptOutput();

        // Step 6: Add to .gitignore
        $this->updateGitignore();

        // Step 7: Generate initial TypeScript files
        $this->generateTypeScriptFiles();

        $this->newLine();
        $this->components->info('✅ Laravel Localizer installed successfully!');
        $this->newLine();

        $this->displayNextSteps();

        return self::SUCCESS;
    }

    /**
     * Publish configuration file.
     */
    private function publishConfig(): void
    {
        if (File::exists(config_path('localizer.php')) && ! $this->option('force')) {
            $this->components->warn('Config file already exists. Use --force to overwrite.');

            return;
        }

        $this->components->task('Publishing configuration', function () {
            $result = $this->call('vendor:publish', [
                '--tag' => 'laravel-localizer-config',
                '--force' => (bool) $this->option('force'),
            ]);

            return $result === 0;
        });
    }

    /**
     * Create default locale files if they don't exist.
     */
    private function createDefaultLocales(): void
    {
        $defaultLocale = $this->config['default'] ?? 'en';
        $langPath = $this->config['path'] ?? lang_path();

        $this->components->task('Creating default locale files', function () use ($defaultLocale, $langPath) {
            // Create en.json if it doesn't exist
            $jsonFile = "{$langPath}/{$defaultLocale}.json";
            if (! File::exists($jsonFile)) {
                File::put($jsonFile, json_encode(new stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            // Create en directory if it doesn't exist
            $localeDir = "{$langPath}/{$defaultLocale}";
            if (! File::exists($localeDir)) {
                File::makeDirectory($localeDir, 0755, true);
            }
        });
    }

    /**
     * Publish middleware file.
     */
    private function publishMiddleware(): void
    {
        if (! confirm('Publish LocalizerMiddleware?', true)) {
            $this->components->info('Skipping middleware...');

            return;
        }

        $this->components->task('Publishing LocalizerMiddleware', function () {
            $result = $this->call('vendor:publish', [
                '--tag' => 'laravel-localizer-middleware',
                '--force' => (bool) $this->option('force'),
            ]);

            return $result === 0;
        });

        // Show middleware registration instructions
        $this->newLine();
        $this->components->warn('⚠️  IMPORTANT: Register the middleware');
        $this->newLine();
        $this->line('  Add to <fg=yellow>bootstrap/app.php</>:');
        $this->newLine();
        $this->line('  <fg=cyan>->withMiddleware(function (Middleware $middleware) {</>');
        $this->line('  <fg=cyan>    $middleware->web(append: [</>');
        $this->line('  <fg=cyan>        \App\Http\Middleware\LocalizerMiddleware::class,</>');
        $this->line('  <fg=cyan>    ]);</>');
        $this->line('  <fg=cyan>})</>');
        $this->newLine();
    }

    /**
     * Install frontend package (React or Vue).
     */
    private function installFrontendPackage(): void
    {
        $framework = $this->option('framework') ?? $this->selectFramework();

        if ($framework === 'skip') {
            $this->components->info('Skipping frontend package installation...');

            return;
        }

        // Determine the npm package to install
        $package = match ($framework) {
            'react' => '@devwizard/laravel-localizer-react',
            'vue' => '@devwizard/laravel-localizer-vue',
            default => null,
        };

        if (! $package) {
            $this->components->warn('Unknown framework. Skipping npm package installation.');

            return;
        }

        // Check if package.json exists
        $packageJsonPath = base_path('package.json');
        if (! File::exists($packageJsonPath)) {
            $this->components->error('package.json not found! Please run npm init first.');

            return;
        }

        // Add package to package.json and install
        $this->addAndInstallNpmPackage($package, $framework);

        // Show Vite configuration instructions
        $this->displayViteConfig($framework, $package);

        // Show bootstrap setup instructions
        $this->displayBootstrapSetup();

        // Show usage example for the selected framework
        $this->displayFrameworkUsage($framework);
    }

    /**
     * Add package to package.json and install via package manager.
     */
    private function addAndInstallNpmPackage(string $package, string $framework): void
    {
        $packageJsonPath = base_path('package.json');
        $packageJson = json_decode(File::get($packageJsonPath), true);

        // Check if already installed
        if (isset($packageJson['dependencies'][$package])) {
            $this->components->info("✓ {$package} is already in package.json");
        } else {
            // Add to dependencies
            $this->components->task("Adding {$package} to package.json", function () use ($package, &$packageJson, $packageJsonPath) {
                $packageJson['dependencies'][$package] = 'latest';

                File::put(
                    $packageJsonPath,
                    json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
                );

                return true;
            });
        }

        // Install using detected package manager
        if (! confirm('Install npm dependencies now?', true)) {
            $this->components->info('⏭️  Skipped npm install. Run manually: npm install');

            return;
        }

        $this->installNpmDependencies();
    }

    /**
     * Install npm dependencies using detected package manager.
     */
    private function installNpmDependencies(): void
    {
        $packageManager = $this->detectPackageManager();

        $this->components->task("Installing dependencies with {$packageManager}", function () use ($packageManager) {
            $command = "{$packageManager} install 2>&1";

            exec($command, $output, $exitCode);

            if ($exitCode !== 0) {
                $this->newLine();
                $this->components->error("Failed to install dependencies with {$packageManager}");
                $this->components->warn('Please run manually:');
                $this->line("  {$packageManager} install");
                $this->newLine();

                return false;
            }

            return true;
        });
    }

    /**
     * Setup TypeScript output directory.
     */
    private function setupTypeScriptOutput(): void
    {
        $outputPath = $this->config['typescript_output_path'] ?? resource_path('js/lang');

        $this->components->task('Setting up TypeScript output directory', function () use ($outputPath) {
            if (! File::exists($outputPath)) {
                File::makeDirectory($outputPath, 0755, true);
            }

            // Create a README in the directory using stub
            $readme = File::get(__DIR__.'/../../stubs/README.md.stub');
            File::put("{$outputPath}/README.md", $readme);
        });
    }

    /**
     * Update .gitignore to ignore generated TypeScript files.
     */
    private function updateGitignore(): void
    {
        $outputPath = $this->config['typescript_output_path'] ?? resource_path('js/lang');
        $relativePath = str_replace(base_path().'/', '', $outputPath);

        $gitignorePath = base_path('.gitignore');

        $this->components->task('Updating .gitignore', function () use ($gitignorePath, $relativePath) {
            if (! File::exists($gitignorePath)) {
                File::put($gitignorePath, '');
            }

            $content = File::get($gitignorePath);

            // Check if already ignored
            if (str_contains($content, $relativePath)) {
                return;
            }

            // Add to .gitignore using stub
            $stub = File::get(__DIR__.'/../../stubs/gitignore.stub');
            $ignoreEntry = str_replace('{{ path }}', $relativePath, $stub);

            File::append($gitignorePath, $ignoreEntry.PHP_EOL);
        });
    }

    /**
     * Generate initial TypeScript files.
     */
    private function generateTypeScriptFiles(): void
    {
        if (! confirm('Generate TypeScript translation files now?', true)) {
            $this->components->info('Skipping generation. Run "php artisan localizer:generate" later.');

            return;
        }

        $this->components->task('Generating TypeScript files', function () {
            $this->call('localizer:generate', ['--all' => true]);
        });
    }

    /**
     * Select frontend framework.
     */
    private function selectFramework(): string
    {
        return select(
            label: 'Which frontend framework are you using?',
            options: [
                'react' => '⚛️  React (with Inertia.js)',
                'vue' => '🍃 Vue 3 (with Inertia.js)',
                'skip' => '⏭️  Skip frontend setup',
            ],
            default: 'react'
        );
    }

    /**
     * Detect package manager.
     */
    private function detectPackageManager(): string
    {
        if (File::exists(base_path('pnpm-lock.yaml'))) {
            return 'pnpm';
        }
        if (File::exists(base_path('yarn.lock'))) {
            return 'yarn';
        }
        if (File::exists(base_path('bun.lockb'))) {
            return 'bun';
        }

        return 'npm';
    }

    /**
     * Display Vite configuration instructions.
     */
    private function displayViteConfig(string $framework, string $package): void
    {
        $this->newLine();
        $this->components->warn('⚠️  IMPORTANT: Add Vite Plugin');
        $this->newLine();

        $importPath = match ($framework) {
            'react' => '@devwizard/laravel-localizer-react/vite',
            'vue' => '@devwizard/laravel-localizer-vue/vite',
            default => '',
        };

        if (! $importPath) {
            return;
        }

        $this->line('  Add to <fg=yellow>vite.config.ts</>:');
        $this->newLine();
        $this->line("  <fg=cyan>import { laravelLocalizer } from '{$importPath}';</>");
        $this->newLine();
        $this->line('  <fg=cyan>export default defineConfig({</>');
        $this->line('  <fg=cyan>  plugins: [</>');
        $this->line('  <fg=cyan>    laravelLocalizer(),  // ← Add this</>');
        $this->line('  <fg=cyan>  ],</>');
        $this->line('  <fg=cyan>});</>');
        $this->newLine();
    }

    /**
     * Display bootstrap setup instructions.
     */
    private function displayBootstrapSetup(): void
    {
        $this->newLine();
        $this->components->warn('⚠️  IMPORTANT: Bootstrap Setup Required');
        $this->newLine();
        $this->line('  Add to <fg=yellow>resources/js/bootstrap.ts</>:');
        $this->newLine();
        $this->line('  <fg=cyan>import * as translations from \'@/lang\';</>');
        $this->newLine();
        $this->line('  <fg=cyan>declare global {</>');
        $this->line('  <fg=cyan>    interface Window {</>');
        $this->line('  <fg=cyan>        localizer: {</>');
        $this->line('  <fg=cyan>            translations: typeof translations;</>');
        $this->line('  <fg=cyan>        };</>');
        $this->line('  <fg=cyan>    }</>');
        $this->line('  <fg=cyan>}</>');
        $this->newLine();
        $this->line('  <fg=cyan>window.localizer = {</>');
        $this->line('  <fg=cyan>    translations,</>');
        $this->line('  <fg=cyan>};</>');
        $this->newLine();
    }

    /**
     * Display framework-specific usage example.
     */
    private function displayFrameworkUsage(string $framework): void
    {
        $this->newLine();
        $this->components->info('💡 Usage Example:');
        $this->newLine();

        if ($framework === 'react') {
            $this->line('  <fg=cyan>import { useLocalizer } from \'@devwizard/laravel-localizer-react\';</>');
            $this->newLine();
            $this->line('  <fg=cyan>function MyComponent() {</>');
            $this->line('  <fg=cyan>  const { __ } = useLocalizer();</>');
            $this->line('  <fg=cyan>  return <h1>{__(\'welcome\')}</h1>;</>');
            $this->line('  <fg=cyan>}</>');
        } elseif ($framework === 'vue') {
            $this->line('  <fg=cyan>import { useTranslation } from \'@devwizard/laravel-localizer-vue\';</>');
            $this->newLine();
            $this->line('  <fg=cyan>const { __ } = useTranslation();</>');
            $this->line('  <fg=cyan><h1>{{ __(\'welcome\') }}</h1></>');
        }

        $this->newLine();
    }

    /**
     * Display next steps.
     */
    private function displayNextSteps(): void
    {
        $this->newLine();
        $this->components->info('📋 Next Steps:');
        $this->newLine();

        $this->line('  1. Configure locales in <fg=yellow>config/localizer.php</>');
        $this->line('  2. Add translation keys to Laravel language files');
        $this->line('  3. Run <fg=yellow>php artisan localizer:sync</> to scan for keys');
        $this->line('  4. Run <fg=yellow>php artisan localizer:translate</> to auto-translate (optional)');

        $this->newLine();
        $this->components->info('📚 Documentation: https://github.com/DevWizardHQ/laravel-localizer');
        $this->newLine();
    }
}
