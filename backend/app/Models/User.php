<?php

namespace App\Models;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'phone',
        'avatar_path',
        'institution',
        'job_title',
        'address',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasRole(RoleEnum|string $role): bool
    {
        return $this->role?->key === ($role instanceof RoleEnum ? $role : RoleEnum::from($role));
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleEnum::SuperAdmin);
    }

    public function isEventOwner(): bool
    {
        return $this->hasRole(RoleEnum::EventOwner);
    }

    public function isCommitteeMember(): bool
    {
        return $this->hasRole(RoleEnum::CommitteeMember);
    }

    public function isParticipant(): bool
    {
        return $this->hasRole(RoleEnum::Participant);
    }

    /**
     * Global permissions granted to this user through their primary role.
     *
     * @return array<int, string>
     */
    public function globalPermissions(): array
    {
        if ($this->isSuperAdmin()) {
            return array_map(fn ($p) => $p->value, PermissionEnum::cases());
        }

        return $this->role?->permissions->pluck('key')?->map(fn ($k) => $k->value)->values()->all() ?? [];
    }

    public function hasGlobalPermission(PermissionEnum|string $permission): bool
    {
        $key = $permission instanceof PermissionEnum ? $permission->value : $permission;

        return in_array($key, $this->globalPermissions(), true);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function eventMembers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EventMember::class);
    }

    /**
     * Event-scoped permission keys this user holds for the given event.
     * Super admins and the event creator implicitly hold every assignable permission.
     *
     * @return array<int, string>
     */
    public function eventPermissionKeys(Event|int $event): array
    {
        if ($this->isSuperAdmin()) {
            return array_map(fn ($p) => $p->value, PermissionEnum::eventAssignable());
        }

        $event = $event instanceof Event ? $event : Event::find($event);

        if ($event !== null && $this->isEventOwner() && $event->created_by === $this->id) {
            return array_map(fn ($p) => $p->value, PermissionEnum::eventAssignable());
        }

        if ($event === null) {
            return [];
        }

        $membership = $this->eventMembers()
            ->where('event_id', $event->id)
            ->with('permissions')
            ->first();

        return $membership?->permissionKeys() ?? [];
    }

    public function hasEventPermission(Event|int $event, PermissionEnum|string $permission): bool
    {
        $key = $permission instanceof PermissionEnum ? $permission->value : $permission;

        return in_array($key, $this->eventPermissionKeys($event), true);
    }
}
