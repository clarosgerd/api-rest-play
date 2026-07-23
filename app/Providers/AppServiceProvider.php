<?php

namespace App\Providers;

use App\Models\Matches;
use App\Observers\MatchObserver;
use App\Services\ApiFootballService;
use App\Services\FootballDataService;
use App\Services\TheSportsDBService;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FootballDataService::class, function () {
            return new FootballDataService;
        });

        $this->app->singleton(TheSportsDBService::class, function () {
            return new TheSportsDBService;
        });

        $this->app->singleton(ApiFootballService::class, function () {
            return new ApiFootballService;
        });
    }

    public function boot(): void
    {
        Matches::observe(MatchObserver::class);

        Schedule::command('predictions:score-unscored')->everyMinute();
    }
}
