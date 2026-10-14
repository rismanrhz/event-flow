<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case EventOwner = 'event_owner';
    case CommitteeMember = 'committee_member';
    case Participant = 'participant';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::EventOwner => 'Event Owner',
            self::CommitteeMember => 'Committee Member',
            self::Participant => 'Participant',
        };
    }
}
