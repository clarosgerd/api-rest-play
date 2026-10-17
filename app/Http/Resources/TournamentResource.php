<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public $preserveKeys = true;

    public function toArray(Request $request): array
    {
        return [
            'tournament_id' => $this->tournament_id,
            'name' => $this->name,
            'season' => $this->season,
            'country' => $this->country,
            'country_code' => $this->country_code,
            'logo_url' => $this->logo_url,
            'flag_url' => $this->flag_url,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'actived' => $this->active,
            'status' => $this->status ?? 'draft',
            'created_by' => $this->created_by,
            'external_id' => $this->external_id,
            'league_category' => $this->league_category,
            'is_promotional' => $this->is_promotional ?? false,
            'promotional_ends_at' => $this->promotional_ends_at,
            'is_currently_promotional' => $this->isCurrentlyPromotional(),
            'stage' => TournamentStageResource::collection($this->whenLoaded('tournamentstages')),
        ];
    }
}
