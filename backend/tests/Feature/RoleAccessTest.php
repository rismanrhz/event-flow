<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_seeder_creates_all_expected_roles(): void
    {
        $this->assertDatabaseHas('roles', ['key' => 'super_admin']);
        $this->assertDatabaseHas('roles', ['key' => 'event_owner']);
        $this->assertDatabaseHas('roles', ['key' => 'committee_member']);
        $this->assertDatabaseHas('roles', ['key' => 'participant']);
    }

    public function test_seeder_creates_all_expected_permissions(): void
    {
        foreach (['view_event', 'edit_event', 'manage_participants', 'manage_tasks', 'manage_schedule', 'manage_budget', 'manage_attendance', 'manage_certificates', 'view_reports'] as $key) {
            $this->assertDatabaseHas('permissions', ['key' => $key]);
        }
    }

    public function test_super_admin_has_all_global_permissions(): void
    {
        $admin = $this->userWithRole('super_admin');

        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue($admin->hasGlobalPermission('manage_users'));
        $this->assertTrue($admin->hasGlobalPermission('view_event'));
        $this->assertContains('edit_event', $admin->globalPermissions());
    }

    public function test_event_owner_role_is_detected(): void
    {
        $owner = $this->userWithRole('event_owner');

        $this->assertTrue($owner->isEventOwner());
        $this->assertFalse($owner->isSuperAdmin());
    }

    public function test_committee_member_role_is_detected(): void
    {
        $member = $this->userWithRole('committee_member');

        $this->assertTrue($member->isCommitteeMember());
        $this->assertFalse($member->hasGlobalPermission('manage_users'));
    }

    public function test_participant_has_no_global_permissions(): void
    {
        $participant = $this->userWithRole('participant');

        $this->assertTrue($participant->isParticipant());
        $this->assertSame([], $participant->globalPermissions());
    }

    public function test_reports_admin_permission_via_role_pivot(): void
    {
        $admin = $this->userWithRole('super_admin');

        $this->assertContains('manage_settings', $admin->globalPermissions());
        $this->assertContains('manage_all_events', $admin->globalPermissions());
        $this->assertContains('view_activity', $admin->globalPermissions());
    }
}
