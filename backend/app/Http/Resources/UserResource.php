<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => [
                'key' => $this->role->key->value,
                'label' => $this->role->key->label(),
            ],
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'institution' => $this->institution,
            'job_title' => $this->job_title,
            'address' => $this->address,
            'is_active' => $this->is_active,
            'permissions' => $this->globalPermissions(),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
