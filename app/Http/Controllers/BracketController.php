<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use App\Services\AdvancementService;

class BracketController extends Controller
{
    public function __construct(
        private AdvancementService $advancementService
    ) {}

    public function index(Tournament $tournament)
    {
        $bracket = $this->advancementService->getBracketStructure($tournament->tournament_id);

        return response()->json([
            'tournament_id' => $tournament->tournament_id,
            'tournament_name' => $tournament->name,
            'bracket' => $bracket,
        ]);
    }

    public function show(Tournament $tournament, int $stageId)
    {
        $bracket = $this->advancementService->getStageBracket($tournament->tournament_id, $stageId);

        return response()->json([
            'tournament_id' => $tournament->tournament_id,
            'tournament_name' => $tournament->name,
            'bracket' => $bracket,
        ]);
    }
}
