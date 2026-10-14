<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To Do',
            self::InProgress => 'In Progress',
            self::Done => 'Done',
        };
    }

    /** Tailwind badge tone key used by the frontend. */
    public function tone(): string
    {
        return match ($this) {
            self::Todo => 'neutral',
            self::InProgress => 'warning',
            self::Done => 'success',
        };
    }
}
