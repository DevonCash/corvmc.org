<?php

namespace Tests\Unit\Policies;

use App\Models\Reservation;
use App\Models\User;
use App\Policies\ReservationPolicy;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReservationPolicyTest extends TestCase
{
    private ReservationPolicy $policy;
    private User $owner;
    private User $manager;
    private User $regularUser;
    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed roles and permissions
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        
        $this->policy = new ReservationPolicy();
        
        // Create test users
        $this->owner = User::factory()->create();
        $this->manager = User::factory()->create();
        $this->regularUser = User::factory()->create();
        
        // Create reservation
        $this->reservation = Reservation::factory()->create([
            'user_id' => $this->owner->id
        ]);
    }

    #[Test]
    public function view_any_allows_all_users()
    {
        $this->assertTrue($this->policy->viewAny($this->owner));
        $this->assertTrue($this->policy->viewAny($this->manager));
        $this->assertTrue($this->policy->viewAny($this->regularUser));
    }

    #[Test]
    public function view_allows_reservation_owner()
    {
        $this->assertTrue($this->policy->view($this->owner, $this->reservation));
    }

    #[Test]
    public function view_denies_non_owners_without_permission()
    {
        $this->assertNull($this->policy->view($this->regularUser, $this->reservation));
    }

    #[Test]
    public function view_allows_users_with_view_reservations_permission()
    {
        // Create admin user with view reservations permission
        $admin = User::factory()->create();
        $admin->assignRole('admin'); // Admin role has all permissions including 'view reservations'
        
        $this->assertTrue($this->policy->view($admin, $this->reservation));
    }

    #[Test]
    public function create_allows_all_users()
    {
        $this->assertTrue($this->policy->create($this->owner));
        $this->assertTrue($this->policy->create($this->manager));
        $this->assertTrue($this->policy->create($this->regularUser));
    }

    #[Test]
    public function update_allows_reservation_owner()
    {
        $this->assertTrue($this->policy->update($this->owner, $this->reservation));
    }

    #[Test]
    public function update_denies_non_owners_without_permission()
    {
        $this->assertFalse($this->policy->update($this->regularUser, $this->reservation));
    }

    #[Test]
    public function update_allows_users_with_manage_practice_space_permission()
    {
        // Create manager user with practice space management permission
        $manager = User::factory()->create();
        $manager->assignRole('practice space manager'); // Has 'manage practice space' permission
        
        $this->assertTrue($this->policy->update($manager, $this->reservation));
    }

    #[Test]
    public function delete_allows_reservation_owner()
    {
        $this->assertTrue($this->policy->delete($this->owner, $this->reservation));
    }

    #[Test]
    public function delete_denies_non_owners_without_permission()
    {
        $this->assertNull($this->policy->delete($this->regularUser, $this->reservation));
    }

    #[Test]
    public function delete_allows_users_with_delete_reservations_permission()
    {
        // Create admin user with delete reservations permission
        $admin = User::factory()->create();
        $admin->assignRole('practice space manager'); // Has 'delete reservations' permission
        
        $this->assertTrue($this->policy->delete($admin, $this->reservation));
    }

    #[Test]
    public function restore_allows_reservation_owner()
    {
        $this->assertTrue($this->policy->restore($this->owner, $this->reservation));
    }

    #[Test]
    public function restore_denies_non_owners_without_permission()
    {
        $this->assertNull($this->policy->restore($this->regularUser, $this->reservation));
    }

    #[Test]
    public function restore_allows_users_with_restore_reservations_permission()
    {
        // Create admin user with restore reservations permission
        $admin = User::factory()->create();
        $admin->assignRole('practice space manager'); // Has 'restore reservations' permission
        
        $this->assertTrue($this->policy->restore($admin, $this->reservation));
    }

    #[Test]
    public function force_delete_denies_all_users()
    {
        $this->assertFalse($this->policy->forceDelete($this->owner, $this->reservation));
        $this->assertFalse($this->policy->forceDelete($this->manager, $this->reservation));
        $this->assertFalse($this->policy->forceDelete($this->regularUser, $this->reservation));
    }

    #[Test]
    public function different_users_different_reservations()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $reservation1 = Reservation::factory()->create(['user_id' => $user1->id]);
        $reservation2 = Reservation::factory()->create(['user_id' => $user2->id]);
        
        // User1 can view/update/delete their own reservation
        $this->assertTrue($this->policy->view($user1, $reservation1));
        $this->assertTrue($this->policy->update($user1, $reservation1));
        $this->assertTrue($this->policy->delete($user1, $reservation1));
        
        // User1 cannot view/update other user's reservation
        $this->assertNull($this->policy->view($user1, $reservation2));
        $this->assertFalse($this->policy->update($user1, $reservation2));
        $this->assertNull($this->policy->delete($user1, $reservation2));
        
        // User2 can view/update/delete their own reservation
        $this->assertTrue($this->policy->view($user2, $reservation2));
        $this->assertTrue($this->policy->update($user2, $reservation2));
        $this->assertTrue($this->policy->delete($user2, $reservation2));
        
        // User2 cannot view/update other user's reservation
        $this->assertNull($this->policy->view($user2, $reservation1));
        $this->assertFalse($this->policy->update($user2, $reservation1));
        $this->assertNull($this->policy->delete($user2, $reservation1));
    }

    #[Test]
    public function permissions_work_across_all_operations()
    {
        // Create admin with all permissions
        $admin = User::factory()->create();
        $admin->assignRole('admin'); // Has all permissions
        
        // Admin can perform all operations on any reservation
        $this->assertTrue($this->policy->view($admin, $this->reservation));
        $this->assertTrue($this->policy->update($admin, $this->reservation));
        $this->assertTrue($this->policy->delete($admin, $this->reservation));
        $this->assertTrue($this->policy->restore($admin, $this->reservation));
        
        // But still cannot force delete
        $this->assertFalse($this->policy->forceDelete($admin, $this->reservation));
    }

    #[Test]
    public function view_any_with_view_reservations_permission()
    {
        // Create admin with view permission
        $admin = User::factory()->create();
        $admin->assignRole('practice space manager'); // Has 'view reservations' permission
        
        $this->assertTrue($this->policy->viewAny($admin));
    }

    #[Test]
    public function policy_handles_null_user_gracefully()
    {
        // These methods don't handle null users in the current implementation,
        // but we can test edge cases with different reservation states
        
        $pendingReservation = Reservation::factory()->create([
            'user_id' => $this->owner->id,
            'status' => 'pending'
        ]);
        
        $confirmedReservation = Reservation::factory()->create([
            'user_id' => $this->owner->id,
            'status' => 'confirmed'
        ]);
        
        $cancelledReservation = Reservation::factory()->create([
            'user_id' => $this->owner->id,
            'status' => 'cancelled'
        ]);
        
        // Owner should be able to access all their reservations regardless of status
        $this->assertTrue($this->policy->view($this->owner, $pendingReservation));
        $this->assertTrue($this->policy->view($this->owner, $confirmedReservation));
        $this->assertTrue($this->policy->view($this->owner, $cancelledReservation));
        
        $this->assertTrue($this->policy->update($this->owner, $pendingReservation));
        $this->assertTrue($this->policy->update($this->owner, $confirmedReservation));
        $this->assertTrue($this->policy->update($this->owner, $cancelledReservation));
    }
}