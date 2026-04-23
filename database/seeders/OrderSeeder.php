<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Seed Orders in various states for testing the Filament admin UI.
     *
     * Prerequisites: users and rehearsal reservations must already exist.
     * Run after ReservationSeeder.
     */
    public function run(): void
    {
        $users = User::whereHas('profile')->get();

        if ($users->isEmpty()) {
            $this->command->warn('No users with member profiles found. Run MemberProfileSeeder first.');
            return;
        }

        $this->command->info('Creating orders in various states...');

        // All reservations are created in the future (to pass validation),
        // then backdated after the order lifecycle completes.

        $this->createCompletedOrders($users, 5);
        $this->createCompletedStripeOrders($users, 3);
        $this->createPendingOrders($users, 3);
        $this->createCompedOrders($users, 2);
        $this->createCancelledOrders($users, 2);
        $this->createRefundedOrders($users, 2);

        $this->command->info('Order seeding complete.');
    }

    private function createCompletedOrders($users, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user = $users->random();
            $reservation = $this->createReservation($user);
            if (! $reservation) { continue; }

            $order = $this->buildOrder($user, $reservation);
            if (! $order) { continue; }

            $committed = Finance::commit($order->fresh(), ['cash' => $order->total_amount]);
            Finance::settle($committed->transactions->first());

            $this->command->line("  Created completed (cash) order #{$committed->id}");
        }
    }

    private function createCompletedStripeOrders($users, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user = $users->random();
            $reservation = $this->createReservation($user);
            if (! $reservation) { continue; }

            $order = $this->buildOrder($user, $reservation);
            if (! $order) { continue; }

            $committed = Finance::commit($order->fresh(), ['stripe' => $order->total_amount]);
            $txn = $committed->transactions->first();
            Finance::settle($txn, 'pi_seed_' . $txn->id);

            $this->command->line("  Created completed (stripe) order #{$committed->id}");
        }
    }

    private function createPendingOrders($users, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user = $users->random();
            $reservation = $this->createReservation($user);
            if (! $reservation) { continue; }

            $order = $this->buildOrder($user, $reservation);
            if (! $order) { continue; }

            Finance::commit($order->fresh(), ['cash' => $order->total_amount]);
            $this->command->line("  Created pending order #{$order->id}");
        }
    }

    private function createCompedOrders($users, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user = $users->random();
            $reservation = $this->createReservation($user);
            if (! $reservation) { continue; }

            $order = $this->buildOrder($user, $reservation);
            if (! $order) { continue; }

            $committed = Finance::commit($order->fresh(), ['cash' => $order->total_amount]);
            Finance::comp($committed);

            $this->command->line("  Created comped order #{$committed->id}");
        }
    }

    private function createCancelledOrders($users, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user = $users->random();
            $reservation = $this->createReservation($user);
            if (! $reservation) { continue; }

            $order = $this->buildOrder($user, $reservation);
            if (! $order) { continue; }

            $committed = Finance::commit($order->fresh(), ['cash' => $order->total_amount]);
            Finance::cancel($committed);

            $this->command->line("  Created cancelled order #{$committed->id}");
        }
    }

    private function createRefundedOrders($users, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user = $users->random();
            $reservation = $this->createReservation($user);
            if (! $reservation) { continue; }

            $order = $this->buildOrder($user, $reservation);
            if (! $order) { continue; }

            $committed = Finance::commit($order->fresh(), ['cash' => $order->total_amount]);
            Finance::settle($committed->transactions->first());
            Finance::refund($committed->fresh());

            $this->command->line("  Created refunded order #{$committed->id}");
        }
    }

    /**
     * Create a reservation for seeding. Uses factory()->make() + forceSave()
     * to bypass watson/validating's after:now rule for past dates.
     */
    private function createReservation(User $user): ?RehearsalReservation
    {
        $daysOffset = rand(-14, 21);
        $startHour = rand(10, 20);
        $duration = rand(1, 3);
        $startTime = Carbon::now()->addDays($daysOffset)->setTime($startHour, 0);
        $endTime = $startTime->copy()->addHours($duration);

        if ($endTime->hour > 22) {
            return null;
        }

        // Check conflicts
        $conflicts = RehearsalReservation::where('reserved_until', '>', $startTime)
            ->where('reserved_at', '<', $endTime)
            ->exists();

        if ($conflicts) {
            return null;
        }

        $reservation = RehearsalReservation::factory()->make([
            'reservable_type' => 'user',
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'status' => \CorvMC\SpaceManagement\States\ReservationState\Confirmed::class,
            'hours_used' => (float) $duration,
            'free_hours_used' => 0,
            'is_recurring' => false,
            'recurrence_pattern' => null,
        ]);

        $reservation->forceSave();

        return $reservation->fresh();
    }

    /**
     * Build an Order with priced LineItems for a reservation.
     */
    private function buildOrder(User $user, RehearsalReservation $reservation): ?Order
    {
        $order = Order::create([
            'user_id' => $user->id,
            'total_amount' => 0,
        ]);

        $lineItems = Finance::price([$reservation]);

        foreach ($lineItems as $lineItem) {
            $lineItem->order_id = $order->id;
            $lineItem->save();
        }

        $order->update(['total_amount' => $lineItems->sum('amount')]);

        // If total is zero (fully discounted), skip — can't commit with 0
        if ($order->total_amount <= 0) {
            $order->delete();
            return null;
        }

        return $order->fresh(['lineItems']);
    }
}
