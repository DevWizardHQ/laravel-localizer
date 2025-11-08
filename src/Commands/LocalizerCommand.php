<?php

namespace DevWizard\Localizer\Commands;

use Illuminate\Console\Command;

class LocalizerCommand extends Command
{
    public $signature = 'laravel-localizer';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
