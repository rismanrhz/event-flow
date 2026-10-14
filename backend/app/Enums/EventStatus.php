<?php

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Tailwind badge tone key used by the frontend. */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Published => 'success',
            self::Completed => 'primary',
            self::Cancelled => 'danger',
        };
    }
}
