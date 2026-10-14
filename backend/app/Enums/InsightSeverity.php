<?php

namespace App\Enums;

enum InsightSeverity: string
{
    case Positive = 'positive';
    case Normal = 'normal';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Positive => 'Positive',
            self::Normal => 'Info',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
        };
    }

    /** Frontend badge tone key. */
    public function tone(): string
    {
        return match ($this) {
            self::Positive => 'success',
            self::Normal => 'neutral',
            self::Warning => 'warning',
            self::Critical => 'danger',
        };
    }
}
