<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TournamentGroupResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'group_id' => $this->group_id,
            'stage_id' => $this->stage_id,
            'name' => $this->name,
            'group_order' => $this->group_order,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'teams' => TournamentGroupTeamResource::collection($this->whenLoaded('teams')),
            'stage' => new TournamentStageResource($this->whenLoaded('stage')),
        ];
    }
}
