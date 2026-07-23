<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'group_id' => $this->group_id,
            'tournament_id' => $this->tournament_id,
            'name' => $this->name,
            'description' => $this->description,
            'creator_id' => $this->creator_id,
            'is_public' => $this->is_public,
            'invitation_code' => $this->invitation_code,
            'max_members' => $this->max_members,
            'created_at' => $this->created_at,
        ];
    }
}
