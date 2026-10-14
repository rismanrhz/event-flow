<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventMemberResource extends JsonResource
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
            'role_title' => $this->role_title,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'role' => $this->user->relationLoaded('role') ? [
                    'key' => $this->user->role?->key->value,
                    'label' => $this->user->role?->key->label(),
                ] : null,
            ]),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissionKeys()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
