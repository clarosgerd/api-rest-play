<?php

namespace App\Console\Commands;

use App\Services\OneFootballScraper;
use Illuminate\Console\Command;

class ScrapeOneFootballCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scrape-one-football-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $scraper = new OneFootballScraper;
        $matches = $scraper->scrapeMatches();

        $this->table(
            ['League', 'Home', 'Score', 'Away', 'Time'],
            collect($matches)->map(function ($match) {
                return [
                    $match['league'],
                    $match['home_team'],
                    $match['score'],
                    $match['away_team'],
                    $match['time'],
                ];
            })->toArray()
        );

        $this->info(count($matches).' matches scraped.');
    }
}
