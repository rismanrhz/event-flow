<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'location' => $this->location,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'capacity' => $this->capacity,
            'status' => [
                'key' => $this->status->value,
                'label' => $this->status->label(),
                'tone' => $this->status->tone(),
            ],
            'cover_url' => $this->cover_url,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'is_member' => $request->user() !== null && $this->eventMembers()
                ->where('user_id', $request->user()->id)
                ->exists(),
            'member_permissions' => $request->user() !== null ? $request->user()->eventPermissionKeys($this->resource) : [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
