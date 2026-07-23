<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'team_id' => $this->team_id,
            'name' => $this->name,
            'league' => $this->league,
            'country' => $this->country,
            'logo_url' => $this->logo_url,
            'flag_url' => $this->flag_url,
            'country_code' => $this->country_code,
            'active' => $this->active,
        ];
    }
}
