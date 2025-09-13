<?php

namespace Tests\Traits;

use App\Models\Band;
use App\Models\Production;
use App\Models\Reservation;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

trait CreatesTestData
{
    /**
     * Create a user with sustaining member subscription (via transactions).
     */
    protected function createUserWithSubscription(float $monthlyAmount = 15.00): User
    {
        $user = User::factory()->create();
        
        // Create monthly subscription transactions for the last 3 months
        for ($i = 0; $i < 3; $i++) {
            Transaction::factory()->create([
                'user_id' => $user->id,
                'amount' => $monthlyAmount * 100, // Store in cents
                'status' => 'completed',
                'type' => 'donation',
                'is_recurring' => true,
                'processed_at' => Carbon::now()->subMonths($i),
            ]);
        }
        
        return $user;
    }

    /**
     * Create a band with multiple members and various roles.
     */
    protected function createBandWithMembers(): Band
    {
        $owner = $this->createBandLeader();
        $guitarist = User::factory()->create();
        $bassist = User::factory()->create();
        $drummer = User::factory()->create();
        $vocalist = User::factory()->create();
        
        $band = Band::factory()->create(['owner_id' => $owner->id]);
        
        // Add members with different roles and positions
        $band->members()->attach([
            $guitarist->id => ['role' => 'member', 'position' => 'guitarist', 'status' => 'active'],
            $bassist->id => ['role' => 'member', 'position' => 'bassist', 'status' => 'active'],
            $drummer->id => ['role' => 'admin', 'position' => 'drummer', 'status' => 'active'],
            $vocalist->id => ['role' => 'member', 'position' => 'vocalist', 'status' => 'invited'],
        ]);
        
        return $band;
    }

    /**
     * Create a production with performers.
     */
    protected function createProductionWithPerformers(int $performerCount = 3): Production
    {
        $manager = User::factory()->create();
        $production = Production::factory()->create([
            'manager_id' => $manager->id,
            'status' => 'published',
            'start_time' => Carbon::now()->addWeek(),
            'end_time' => Carbon::now()->addWeek()->addHours(3),
        ]);
        
        // Add performer bands
        for ($i = 0; $i < $performerCount; $i++) {
            $band = $this->createBand();
            $production->performers()->attach($band->id, [
                'set_order' => $i + 1,
                'set_duration' => 30 + ($i * 15), // Varying set lengths
                'status' => 'confirmed',
            ]);
        }
        
        return $production;
    }

    /**
     * Create conflicting reservations for testing.
     */
    protected function createConflictingReservations(): array
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        
        $baseTime = Carbon::tomorrow()->setHour(14); // 2 PM tomorrow
        
        $reservation1 = Reservation::factory()->create([
            'user_id' => $user1->id,
            'start_time' => $baseTime,
            'end_time' => $baseTime->copy()->addHours(2),
            'status' => 'confirmed',
        ]);
        
        $reservation2 = Reservation::factory()->create([
            'user_id' => $user2->id,
            'start_time' => $baseTime->copy()->addHour(), // Overlaps by 1 hour
            'end_time' => $baseTime->copy()->addHours(3),
            'status' => 'confirmed',
        ]);
        
        return [$reservation1, $reservation2];
    }

    /**
     * Create a series of transactions for testing revenue calculations.
     */
    protected function createRevenueTransactions(): array
    {
        $users = User::factory()->count(5)->create();
        $transactions = [];
        
        foreach ($users as $i => $user) {
            // Mix of different transaction types
            $transactions[] = Transaction::factory()->create([
                'user_id' => $user->id,
                'amount' => 1500, // $15.00 reservation
                'type' => 'reservation',
                'status' => 'completed',
            ]);
            
            $transactions[] = Transaction::factory()->create([
                'user_id' => $user->id,
                'amount' => 2500, // $25.00 donation
                'type' => 'donation',
                'status' => 'completed',
            ]);
        }
        
        return $transactions;
    }

    /**
     * Create test data for member profile testing.
     */
    protected function createMemberWithProfile(): User
    {
        $user = User::factory()->create();
        
        // Add skills and tags
        $user->attachTags(['guitar', 'vocals'], 'skill');
        $user->attachTags(['rock', 'indie'], 'genre');
        $user->attachTags(['radiohead', 'arctic monkeys'], 'influence');
        
        return $user;
    }

    /**
     * Create a reservation with associated transaction.
     */
    protected function createPaidReservation(float $amount = 15.00): Reservation
    {
        $user = User::factory()->create();
        
        $reservation = Reservation::factory()->create([
            'user_id' => $user->id,
            'cost' => $amount * 100, // Store in cents
            'status' => 'confirmed',
        ]);
        
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'transactionable_type' => Reservation::class,
            'transactionable_id' => $reservation->id,
            'amount' => $amount * 100,
            'type' => 'reservation',
            'status' => 'completed',
        ]);
        
        return $reservation;
    }

    /**
     * Create test data for time-based scenarios.
     */
    protected function createTimeBasedReservations(): array
    {
        $user = User::factory()->create();
        $now = Carbon::now();
        
        return [
            // Past reservation
            Reservation::factory()->create([
                'user_id' => $user->id,
                'start_time' => $now->copy()->subDays(2),
                'end_time' => $now->copy()->subDays(2)->addHours(2),
                'status' => 'completed',
            ]),
            
            // Current reservation (in progress)
            Reservation::factory()->create([
                'user_id' => $user->id,
                'start_time' => $now->copy()->subHour(),
                'end_time' => $now->copy()->addHour(),
                'status' => 'confirmed',
            ]),
            
            // Future reservation
            Reservation::factory()->create([
                'user_id' => $user->id,
                'start_time' => $now->copy()->addDays(2),
                'end_time' => $now->copy()->addDays(2)->addHours(2),
                'status' => 'confirmed',
            ]),
        ];
    }
}