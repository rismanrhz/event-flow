<?php

namespace App\Models;

use App\Enums\ParticipantStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventParticipant extends Model
{
    use HasFactory, SoftDeletes;

    public const DEFAULT_STATUS = 'registered';

    protected $fillable = [
        'event_id',
        'user_id',
        'registration_code',
        'status',
        'checked_in_at',
        'cancelled_at',
        'cancel_reason',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'status' => ParticipantStatus::class,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCancelled(): bool
    {
        return $this->status === ParticipantStatus::Cancelled;
    }

    public function isWaitlisted(): bool
    {
        return $this->status === ParticipantStatus::Waitlisted;
    }

    public function isCheckedIn(): bool
    {
        return $this->status === ParticipantStatus::CheckedIn;
    }

    public function markCheckedIn(): void
    {
        $this->update([
            'status' => ParticipantStatus::CheckedIn,
            'checked_in_at' => now(),
        ]);
    }

    public function cancel(?string $reason = null): void
    {
        $this->update([
            'status' => ParticipantStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);
    }
}
