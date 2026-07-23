<?php

namespace App\Observers;

use App\Jobs\ScoreMatchPredictions;
use App\Models\Matches;

class MatchObserver
{
    public function updated(Matches $match): void
    {
        if ($match->isDirty('status') && $match->status === 'finished'
            && $match->home_score !== null && $match->away_score !== null) {
            ScoreMatchPredictions::dispatch($match->match_id);
        }
    }
}
