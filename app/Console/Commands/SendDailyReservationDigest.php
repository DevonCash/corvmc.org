<?php

namespace App\Console\Commands;

use CorvMC\SpaceManagement\Models\RehearsalReservation;
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
    protected $description = 'Send daily digest of tomorrow\'s reservations to admins';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Sending daily reservation digest...');

        $tomorrow = now()->addDay();

        // Get all reservations for tomorrow, ordered by start time
        $tomorrowsReservations = RehearsalReservation::with('reservable')
            ->whereDate('reserved_at', $tomorrow)
            ->whereIn('status', ['confirmed', 'pending'])
            ->orderBy('reserved_at')
            ->get();

        $count = $tomorrowsReservations->count();
        $date = $tomorrow->format('l, F j, Y');

        // Get all admin users
        $admins = User::role('admin')->get();

        if ($admins->isEmpty()) {
            $this->warn('No admin users found to send digest to.');

            return Command::FAILURE;
        }

        // Send digest to all admins
        Notification::send($admins, new DailyReservationDigestNotification($tomorrowsReservations, $tomorrow));

        if ($count === 0) {
            $this->info("Sent digest to {$admins->count()} admin(s): No reservations for {$date}");
        } else {
            $this->info("Sent digest to {$admins->count()} admin(s): {$count} reservation(s) for {$date}");
        }

        return Command::SUCCESS;
    }
}
