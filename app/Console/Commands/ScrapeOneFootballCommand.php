<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * NOTE: This command is currently disabled because OneFootballScraper
 * service class does not exist. To re-enable, create the service at
 * app/Services/OneFootballScraper.php with a scrapeMatches() method.
 */
class ScrapeOneFootballCommand extends Command
{
    protected $signature = 'app:scrape-one-football-command';

    protected $description = 'Scrape match data from OneFootball (DISABLED - service not implemented)';

    public function handle()
    {
        $this->error('OneFootballScraper service is not implemented.');
        $this->info('To enable this command, create app/Services/OneFootballScraper.php');
        $this->info('with a scrapeMatches() method that returns match data.');

        return 1;
    }
}

