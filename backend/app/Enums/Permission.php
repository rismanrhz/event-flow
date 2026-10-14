<?php

namespace App\Enums;

enum Permission: string
{
    // Event-scoped permissions (assignable to committee members)
    case ViewEvent = 'view_event';
    case EditEvent = 'edit_event';
    case ManageParticipants = 'manage_participants';
    case ManageTasks = 'manage_tasks';
    case ManageSchedule = 'manage_schedule';
    case ManageBudget = 'manage_budget';
    case ManageAttendance = 'manage_attendance';
    case ManageCertificates = 'manage_certificates';
    case ViewReports = 'view_reports';

    // Global permissions (system-wide)
    case ManageUsers = 'manage_users';
    case ManageSettings = 'manage_settings';
    case ManageAllEvents = 'manage_all_events';
    case ViewActivity = 'view_activity';

    public function label(): string
    {
        return match ($this) {
            self::ViewEvent => 'View Event',
            self::EditEvent => 'Edit Event',
            self::ManageParticipants => 'Manage Participants',
            self::ManageTasks => 'Manage Tasks',
            self::ManageSchedule => 'Manage Schedule',
            self::ManageBudget => 'Manage Budget',
            self::ManageAttendance => 'Manage Attendance',
            self::ManageCertificates => 'Manage Certificates',
            self::ViewReports => 'View Reports',
            self::ManageUsers => 'Manage Users',
            self::ManageSettings => 'Manage Settings',
            self::ManageAllEvents => 'Manage All Events',
            self::ViewActivity => 'View Activity',
        };
    }

    public function module(): string
    {
        return match ($this) {
            self::ViewEvent, self::EditEvent => 'events',
            self::ManageParticipants, self::ManageAttendance => 'participants',
            self::ManageTasks => 'tasks',
            self::ManageSchedule => 'schedules',
            self::ManageBudget => 'budgets',
            self::ManageCertificates => 'certificates',
            self::ViewReports => 'reports',
            self::ManageUsers => 'users',
            self::ManageSettings => 'settings',
            self::ManageAllEvents, self::ViewActivity => 'system',
        };
    }

    /** Permissions that can be granted to a committee member for a specific event. */
    public static function eventAssignable(): array
    {
        return [
            self::ViewEvent,
            self::EditEvent,
            self::ManageParticipants,
            self::ManageTasks,
            self::ManageSchedule,
            self::ManageBudget,
            self::ManageAttendance,
            self::ManageCertificates,
            self::ViewReports,
        ];
    }

    /** Values of the event-scoped permissions. */
    public static function eventAssignableKeys(): array
    {
        return array_map(fn (self $permission) => $permission->value, self::eventAssignable());
    }
}
