<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'match_id' => $this->match_id,
            'home_team_id' => $this->home_team_id,
            'away_team_id' => $this->away_team_id,
            'tournament_id' => $this->tournament_id,
            'stage_id' => $this->stage_id,
            'match_date' => $this->match_date,
            'status' => $this->status,
            'home_score' => $this->home_score,
            'away_score' => $this->away_score,
            'stage' => $this->relationLoaded('stage') ? new TournamentStageResource($this->stage) : null,
            'home' => $this->relationLoaded('home') ? new TeamResource($this->home) : null,
            'away' => $this->relationLoaded('away') ? new TeamResource($this->away) : null,
        ];
    }
}
