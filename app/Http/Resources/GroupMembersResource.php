<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupMembersResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'member_id' => $this->member_id,
            'group_id' => $this->group_id,
            'user_id' => $this->user_id,
            'joined_at' => $this->joined_at,
            'is_admin' => $this->is_admin,
            'status' => $this->status,
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
