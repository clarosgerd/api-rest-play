<?php

namespace App\Http\Controllers;

use App\Models\TournamentAdvancement;
use App\Models\TournamentAdvancementRule;
use App\Models\TournamentGroup;
use App\Models\TournamentStage;
use App\Services\AdvancementService;
use Illuminate\Http\Request;

class AdvancementController extends Controller
{
    public function __construct(
        private AdvancementService $advancementService
    ) {}

    // ── Advancement Rules ──

    public function indexRules(TournamentStage $stage)
    {
        $rules = TournamentAdvancementRule::where('stage_id', $stage->stage_id)
            ->with('nextStage')
            ->get();

        return response()->json([
            'stage_id' => $stage->stage_id,
            'rules' => $rules,
        ]);
    }

    public function storeRule(Request $request, TournamentStage $stage)
    {
        $validated = $request->validate([
            'next_stage_id' => ['required', 'integer', 'exists:tournamentstage,stage_id'],
            'rule_type' => ['required', 'string', 'in:top_n,best_third,manual'],
            'rule_value' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['stage_id'] = $stage->stage_id;

        $rule = TournamentAdvancementRule::create($validated);

        return response()->json([
            'message' => 'Advancement rule created successfully',
            'rule' => $rule,
        ], 201);
    }

    public function updateRule(Request $request, TournamentAdvancementRule $rule)
    {
        $validated = $request->validate([
            'next_stage_id' => ['sometimes', 'required', 'integer', 'exists:tournamentstage,stage_id'],
            'rule_type' => ['sometimes', 'required', 'string', 'in:top_n,best_third,manual'],
            'rule_value' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $rule->update($validated);

        return response()->json([
            'message' => 'Advancement rule updated successfully',
            'rule' => $rule,
        ]);
    }

    public function destroyRule(TournamentAdvancementRule $rule)
    {
        $rule->delete();

        return response()->json([
            'message' => 'Advancement rule deleted successfully',
        ]);
    }

    // ── Advancement (teams advancing) ──

    public function listAdvanced(TournamentStage $stage)
    {
        $advancements = TournamentAdvancement::whereHas('rule', function ($q) use ($stage) {
            $q->where('stage_id', $stage->stage_id);
        })->with(['team', 'sourceGroup', 'rule.nextStage'])->get();

        return response()->json([
            'stage_id' => $stage->stage_id,
            'advancements' => $advancements,
        ]);
    }

    public function suggest(TournamentStage $stage)
    {
        $suggested = $this->advancementService->suggestAdvancements($stage->stage_id);

        return response()->json([
            'stage_id' => $stage->stage_id,
            'message' => count($suggested).' team(s) suggested to advance',
            'suggested' => $suggested,
        ]);
    }

    public function confirm(TournamentAdvancement $advancement)
    {
        $this->advancementService->confirmAdvancement($advancement->advancement_id);

        $advancement->refresh();

        return response()->json([
            'message' => 'Advancement confirmed',
            'advancement' => $advancement,
        ]);
    }

    public function confirmAll(TournamentStage $stage)
    {
        $confirmed = $this->advancementService->confirmAllAdvancements($stage->stage_id);

        return response()->json([
            'stage_id' => $stage->stage_id,
            'message' => "{$confirmed} advancement(s) confirmed",
            'confirmed_count' => $confirmed,
        ]);
    }

    // ── Standings (CONMEBOL style) ──

    public function standings(TournamentStage $stage)
    {
        $standings = $this->advancementService->calculateStageStandings($stage->stage_id);

        return response()->json([
            'stage_id' => $stage->stage_id,
            'stage_name' => $stage->name,
            'standings' => $standings,
        ]);
    }

    public function groupStandings(TournamentStage $stage, TournamentGroup $group)
    {
        $standings = $this->advancementService->calculateGroupStandings($group->group_id);

        return response()->json([
            'stage_id' => $stage->stage_id,
            'group_id' => $group->group_id,
            'group_name' => $group->name,
            'standings' => $standings,
        ]);
    }
}
