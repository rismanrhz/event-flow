<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EventMember extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'user_id', 'role_title'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'event_member_permissions');
    }

    /**
     * Event-scoped permission keys held by this membership.
     *
     * @return array<int, string>
     */
    public function permissionKeys(): array
    {
        return $this->permissions->pluck('key')->map(fn ($key) => $key->value)->values()->all();
    }
}
