<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TournamentGroupTeamResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'team_id' => $this->team_id,
            'team_name' => $this->team->name ?? 'Unknown',
            'position_final' => $this->position_final,
            'points' => $this->points,
            'goal_diff' => $this->goal_diff,
            'goals_for' => $this->goals_for,
            'goals_against' => $this->goals_against,
            'matches_played' => $this->matches_played,
            'wins' => $this->wins,
            'draws' => $this->draws,
            'losses' => $this->losses,
        ];
    }
}
