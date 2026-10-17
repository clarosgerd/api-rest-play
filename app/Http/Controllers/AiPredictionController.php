<?php

namespace App\Http\Controllers;

use App\Models\Matches;
use App\Models\Tournament;
use App\Services\PredictionEngineService;
use Illuminate\Http\JsonResponse;

class AiPredictionController extends Controller
{
    public function __construct(
        protected PredictionEngineService $engine
    ) {}

    public function show(Matches $match): JsonResponse
    {
        // Only reveal AI prediction after the match is finished
        if ($match->status !== 'finished') {
            return response()->json(['data' => null]);
        }

        $result = $this->engine->predict($match->match_id);

        if (! $result) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $result]);
    }

    public function tournament(Tournament $tournament): JsonResponse
    {
        $results = $this->engine->predictTournament($tournament->tournament_id);

        return response()->json(['data' => $results]);
    }

    public function accuracy(): JsonResponse
    {
        return response()->json(['data' => $this->engine->accuracyStats()]);
    }
}
