<?php

namespace App\Services;

use App\Models\Matches;
use App\Models\TournamentAdvancement;
use App\Models\TournamentAdvancementRule;
use App\Models\TournamentGroup;
use App\Models\TournamentGroupTeam;
use App\Models\TournamentStage;
use Carbon\Carbon;

class AdvancementService
{
    public function calculateGroupStandings(int $groupId): array
    {
        $groupTeams = TournamentGroupTeam::where('group_id', $groupId)
            ->with('team')
            ->get();

        $standings = $groupTeams->map(function ($gt) {
            return [
                'team_id' => $gt->team_id,
                'team_name' => $gt->team->name ?? 'Unknown',
                'logo_url' => $gt->team->logo_url ?? null,
                'matches_played' => $gt->matches_played,
                'wins' => $gt->wins,
                'draws' => $gt->draws,
                'losses' => $gt->losses,
                'goals_for' => $gt->goals_for,
                'goals_against' => $gt->goals_against,
                'goal_diff' => $gt->goal_diff,
                'points' => $gt->points,
            ];
        })->sortByDesc('points')
            ->sortByDesc('goal_diff')
            ->sortByDesc('goals_for')
            ->values()
            ->toArray();

        foreach ($standings as $index => &$entry) {
            $entry['position'] = $index + 1;
        }

        return $standings;
    }

    public function calculateStageStandings(int $stageId): array
    {
        $groups = TournamentGroup::where('stage_id', $stageId)
            ->where('status', 'approved')
            ->get();

        $allStandings = [];

        foreach ($groups as $group) {
            $groupStandings = $this->calculateGroupStandings($group->group_id);
            $allStandings[] = [
                'group_id' => $group->group_id,
                'group_name' => $group->name,
                'standings' => $groupStandings,
            ];
        }

        return $allStandings;
    }

    public function suggestAdvancements(int $stageId): array
    {
        $rules = TournamentAdvancementRule::where('stage_id', $stageId)->get();
        $suggested = [];

        foreach ($rules as $rule) {
            $advancements = $this->calculateAdvancementsForRule($rule);

            foreach ($advancements as $adv) {
                $exists = TournamentAdvancement::where('rule_id', $rule->rule_id)
                    ->where('team_id', $adv['team_id'])
                    ->exists();

                if (! $exists) {
                    $newAdvancement = TournamentAdvancement::create([
                        'rule_id' => $rule->rule_id,
                        'team_id' => $adv['team_id'],
                        'source_group_id' => $adv['source_group_id'] ?? null,
                        'source_position' => $adv['source_position'] ?? null,
                        'confirmed' => false,
                    ]);

                    $suggested[] = [
                        'advancement_id' => $newAdvancement->advancement_id,
                        'team_id' => $adv['team_id'],
                        'team_name' => $adv['team_name'] ?? 'Unknown',
                        'rule_type' => $rule->rule_type,
                        'source_group_id' => $adv['source_group_id'] ?? null,
                        'source_position' => $adv['source_position'] ?? null,
                    ];
                }
            }
        }

        return $suggested;
    }

    public function confirmAdvancement(int $advancementId): bool
    {
        $advancement = TournamentAdvancement::find($advancementId);

        if (! $advancement) {
            return false;
        }

        $advancement->update([
            'confirmed' => true,
            'confirmed_at' => Carbon::now(),
        ]);

        return true;
    }

    public function confirmAllAdvancements(int $stageId): int
    {
        $advancements = TournamentAdvancement::whereHas('rule', function ($q) use ($stageId) {
            $q->where('stage_id', $stageId);
        })->where('confirmed', false)
            ->get();

        $confirmed = 0;

        foreach ($advancements as $advancement) {
            $advancement->update([
                'confirmed' => true,
                'confirmed_at' => Carbon::now(),
            ]);
            $confirmed++;
        }

        return $confirmed;
    }

    public function getBracketStructure(int $tournamentId): array
    {
        $stages = TournamentStage::where('tournament_id', $tournamentId)
            ->with(['tournamentGroups.teams.team', 'matches.home', 'matches.away'])
            ->orderBy('stage_order')
            ->get();

        $bracket = [];

        foreach ($stages as $stage) {
            $stageData = [
                'stage_id' => $stage->stage_id,
                'name' => $stage->name,
                'is_knockout' => $stage->is_knockout,
                'stage_order' => $stage->stage_order,
            ];

            if (! $stage->is_knockout) {
                $stageData['groups'] = $stage->tournamentGroups->map(function ($group) {
                    $standings = $this->calculateGroupStandings($group->group_id);

                    return [
                        'group_id' => $group->group_id,
                        'name' => $group->name,
                        'status' => $group->status,
                        'teams' => $group->teams->map(function ($gt) {
                            return [
                                'team_id' => $gt->team_id,
                                'team_name' => $gt->team->name ?? 'Unknown',
                                'position_final' => $gt->position_final,
                                'points' => $gt->points,
                                'goal_diff' => $gt->goal_diff,
                            ];
                        }),
                        'standings' => $standings,
                        'matches' => $this->getGroupMatches($group),
                    ];
                });
            } else {
                $stageData['matches'] = $stage->matches->map(function ($match) {
                    return [
                        'match_id' => $match->match_id,
                        'home' => [
                            'team_id' => $match->home_team_id,
                            'name' => $match->home->name ?? 'TBD',
                        ],
                        'away' => [
                            'team_id' => $match->away_team_id,
                            'name' => $match->away->name ?? 'TBD',
                        ],
                        'home_score' => $match->home_score,
                        'away_score' => $match->away_score,
                        'status' => $match->status,
                        'match_date' => $match->match_date,
                    ];
                });

                $advancements = TournamentAdvancement::whereHas('rule', function ($q) use ($stage) {
                    $q->where('stage_id', $stage->stage_id);
                })->with(['team', 'rule.nextStage'])
                    ->get();

                $stageData['advancements'] = $advancements->map(function ($adv) {
                    return [
                        'advancement_id' => $adv->advancement_id,
                        'team_id' => $adv->team_id,
                        'team_name' => $adv->team->name ?? 'Unknown',
                        'confirmed' => $adv->confirmed,
                        'source_group_id' => $adv->source_group_id,
                        'source_position' => $adv->source_position,
                    ];
                });
            }

            $bracket[] = $stageData;
        }

        return $bracket;
    }

    public function getStageBracket(int $tournamentId, int $stageId): array
    {
        $stages = $this->getBracketStructure($tournamentId);

        foreach ($stages as $stage) {
            if ($stage['stage_id'] == $stageId) {
                return $stage;
            }
        }

        return [];
    }

    private function calculateAdvancementsForRule(TournamentAdvancementRule $rule): array
    {
        $results = [];

        if ($rule->rule_type === 'top_n') {
            $groups = TournamentGroup::where('stage_id', $rule->stage_id)
                ->where('status', 'approved')
                ->get();

            foreach ($groups as $group) {
                $standings = $this->calculateGroupStandings($group->group_id);
                $topN = array_slice($standings, 0, $rule->rule_value ?? 2);

                foreach ($topN as $entry) {
                    $results[] = [
                        'team_id' => $entry['team_id'],
                        'team_name' => $entry['team_name'],
                        'source_group_id' => $group->group_id,
                        'source_position' => $entry['position'],
                    ];
                }
            }
        } elseif ($rule->rule_type === 'best_third') {
            $groups = TournamentGroup::where('stage_id', $rule->stage_id)
                ->where('status', 'approved')
                ->get();

            $thirdPlaces = [];

            foreach ($groups as $group) {
                $standings = $this->calculateGroupStandings($group->group_id);
                if (isset($standings[2])) {
                    $third = $standings[2];
                    $third['source_group_id'] = $group->group_id;
                    $thirdPlaces[] = $third;
                }
            }

            usort($thirdPlaces, function ($a, $b) {
                if ($b['points'] !== $a['points']) {
                    return $b['points'] <=> $a['points'];
                }
                if ($b['goal_diff'] !== $a['goal_diff']) {
                    return $b['goal_diff'] <=> $a['goal_diff'];
                }

                return $b['goals_for'] <=> $a['goals_for'];
            });

            $bestThird = array_slice($thirdPlaces, 0, $rule->rule_value ?? 8);

            foreach ($bestThird as $entry) {
                $results[] = [
                    'team_id' => $entry['team_id'],
                    'team_name' => $entry['team_name'],
                    'source_group_id' => $entry['source_group_id'],
                    'source_position' => 3,
                ];
            }
        }

        return $results;
    }

    private function getGroupMatches(TournamentGroup $group): array
    {
        $teamIds = $group->teams->pluck('team_id')->toArray();

        if (empty($teamIds)) {
            return [];
        }

        $matches = Matches::where('stage_id', $group->stage_id)
            ->whereIn('home_team_id', $teamIds)
            ->whereIn('away_team_id', $teamIds)
            ->with(['home', 'away'])
            ->get()
            ->toArray();

        return $matches;
    }
}
