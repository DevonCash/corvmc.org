<?php

namespace App\Console\Commands;

use App\Models\RehearsalReservation;
use App\Models\User;
use App\Notifications\DailyReservationDigestNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendDailyReservationDigest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:daily-digest';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily digest of today\'s reservations to admins';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Sending daily reservation digest...');

        // Get all reservations for today, ordered by start time
        $todaysReservations = RehearsalReservation::whereDate('reserved_at', today())
            ->whereIn('status', ['confirmed', 'pending'])
            ->orderBy('reserved_at')
            ->get();

        $count = $todaysReservations->count();
        $date = now()->format('l, F j, Y');

        // Get all admin users
        $admins = User::role('admin')->get();

        if ($admins->isEmpty()) {
            $this->warn('No admin users found to send digest to.');
            return Command::FAILURE;
        }

        // Send digest to all admins
        Notification::send($admins, new DailyReservationDigestNotification($todaysReservations));

        if ($count === 0) {
            $this->info("Sent digest to {$admins->count()} admin(s): No reservations for {$date}");
        } else {
            $this->info("Sent digest to {$admins->count()} admin(s): {$count} reservation(s) for {$date}");
        }

        return Command::SUCCESS;
    }
}
