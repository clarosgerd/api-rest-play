<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CountryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'country_id' => $this->country_id,
            'name' => $this->name,
            'code' => $this->code,
            'flag_url' => $this->flag_url,
        ];
    }
}
