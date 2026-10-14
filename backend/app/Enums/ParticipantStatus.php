<?php

namespace App\Enums;

enum ParticipantStatus: string
{
    case Registered = 'registered';
    case CheckedIn = 'checked_in';
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registered',
            self::CheckedIn => 'Checked in',
            self::Waitlisted => 'Waitlisted',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Registered => 'primary',
            self::CheckedIn => 'success',
            self::Waitlisted => 'warning',
            self::Cancelled => 'danger',
        };
    }
}
