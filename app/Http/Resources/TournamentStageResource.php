<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentStageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    // public static $wrap = null;
    // public $preserveKeys = true;
    public function toArray(Request $request): array
    {
        // dd($request);
        return [
            'stage_id' => $this->stage_id,
            'tournament_id' => $this->tournament_id,
            'name' => $this->name,
            'stage_order' => $this->stage_order,
            'is_knockout' => $this->is_knockout,
        ];
    }
}
