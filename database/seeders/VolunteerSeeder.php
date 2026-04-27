<?php

namespace Database\Seeders;

use App\Models\User;
use CorvMC\Events\Models\Event;
use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Models\Position;
use CorvMC\Volunteering\Models\Shift;
use CorvMC\Volunteering\Services\HourLogService;
use CorvMC\Volunteering\States\HourLogState\CheckedIn;
use CorvMC\Volunteering\States\HourLogState\Confirmed;
use CorvMC\Volunteering\States\HourLogState\Interested;
use CorvMC\Volunteering\States\HourLogState\Pending;
use Illuminate\Database\Seeder;
use Spatie\Tags\Tag;

class VolunteerSeeder extends Seeder
{
    private HourLogService $hourLogService;

    public function run(): void
    {
        $this->hourLogService = app(HourLogService::class);

        $users = User::take(10)->get();
        if ($users->count() < 5) {
            $this->command->warn('Need at least 5 users. Run MemberProfileSeeder first.');

            return;
        }

        $staff = User::role('admin')->first() ?? $users->first();
        $events = Event::take(8)->get();

        $this->command->info('Creating volunteer positions...');
        $positions = $this->createPositions();

        $this->command->info('Creating volunteer shifts...');
        $shifts = $this->createShifts($positions, $events);

        $this->command->info('Seeding shift lifecycle — all states...');
        $this->seedShiftLifecycle($shifts, $users, $staff);

        $this->command->info('Seeding self-reported hours — all states...');
        $this->seedSelfReportedLifecycle($positions, $users, $staff);

        $this->command->info('Seeding completed event with full volunteer history...');
        $this->seedCompletedEventScenario($positions, $events, $users, $staff);

        $this->command->info('Volunteer seeding complete.');
    }

    private function createPositions(): array
    {
        $positionData = [
            ['title' => 'Sound Engineer', 'description' => 'Operates the sound board and manages audio equipment during events.', 'tags' => ['technical', 'events']],
            ['title' => 'Door Person', 'description' => 'Collects tickets/cover, manages entry, checks IDs.', 'tags' => ['front-of-house', 'events']],
            ['title' => 'Bartender', 'description' => 'Serves beverages, manages cash box, maintains bar area.', 'tags' => ['front-of-house', 'events']],
            ['title' => 'Load-In Helper', 'description' => 'Assists bands with loading gear in and out of the venue.', 'tags' => ['physical', 'events']],
            ['title' => 'Grant Writer', 'description' => 'Researches and writes grant applications for the collective.', 'tags' => ['admin', 'fundraising']],
            ['title' => 'Space Cleaner', 'description' => 'Deep cleans practice rooms and common areas.', 'tags' => ['maintenance']],
            ['title' => 'Social Media', 'description' => 'Creates and posts event promotions on social media.', 'tags' => ['marketing', 'admin']],
            ['title' => 'Poster Designer', 'description' => 'Designs show posters and flyers for upcoming events.', 'tags' => ['marketing', 'creative']],
        ];

        $positions = [];
        foreach ($positionData as $data) {
            $tags = $data['tags'];
            unset($data['tags']);
            $position = Position::create($data);
            $position->attachTags($tags);
            $positions[] = $position;
        }

        return $positions;
    }

    private function createShifts(array $positions, $events): array
    {
        $shifts = [];

        // Upcoming shifts linked to events
        foreach ($events->take(4) as $i => $event) {
            // Sound + Door for each event
            foreach ([$positions[0], $positions[1]] as $position) {
                $startAt = $event->start_datetime ?? now()->addDays($i + 2)->setHour(19);
                $shifts[] = Shift::create([
                    'position_id' => $position->id,
                    'event_id' => $event->id,
                    'start_at' => $startAt,
                    'end_at' => (clone $startAt)->addHours(4),
                    'capacity' => 2,
                ]);
            }

            // Bartender for first 2 events
            if ($i < 2) {
                $startAt = $event->start_datetime ?? now()->addDays($i + 2)->setHour(19);
                $shifts[] = Shift::create([
                    'position_id' => $positions[2]->id,
                    'event_id' => $event->id,
                    'start_at' => $startAt,
                    'end_at' => (clone $startAt)->addHours(4),
                    'capacity' => 2,
                ]);
            }
        }

        // Standalone shifts (no event) — upcoming
        $shifts[] = Shift::create([
            'position_id' => $positions[5]->id, // Space Cleaner
            'start_at' => now()->addDays(3)->setHour(10),
            'end_at' => now()->addDays(3)->setHour(14),
            'capacity' => 4,
        ]);

        $shifts[] = Shift::create([
            'position_id' => $positions[3]->id, // Load-In Helper
            'start_at' => now()->addDays(5)->setHour(16),
            'end_at' => now()->addDays(5)->setHour(19),
            'capacity' => 3,
        ]);

        // Shift happening right now (for testing check-in/out)
        $shifts[] = Shift::create([
            'position_id' => $positions[1]->id, // Door Person
            'event_id' => $events->first()?->id,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHours(3),
            'capacity' => 2,
        ]);

        // Past shifts (completed events)
        for ($i = 0; $i < 3; $i++) {
            $pastEvent = $events->skip(4)->values()->get($i);
            $startAt = now()->subWeeks($i + 1)->setHour(19);
            $shifts[] = Shift::create([
                'position_id' => $positions[$i]->id,
                'event_id' => $pastEvent?->id,
                'start_at' => $startAt,
                'end_at' => (clone $startAt)->addHours(4),
                'capacity' => 3,
            ]);
        }

        return $shifts;
    }

    /**
     * Create hour logs covering every shift lifecycle state.
     */
    private function seedShiftLifecycle(array $shifts, $users, User $staff): void
    {
        // --- Interested (signed up, waiting for confirmation) ---
        if (isset($shifts[0])) {
            HourLog::create([
                'user_id' => $users[0]->id,
                'shift_id' => $shifts[0]->id,
                'status' => Interested::class,
            ]);
            HourLog::create([
                'user_id' => $users[1]->id,
                'shift_id' => $shifts[0]->id,
                'status' => Interested::class,
            ]);
        }

        // --- Confirmed (approved, waiting for day-of) ---
        if (isset($shifts[1])) {
            HourLog::create([
                'user_id' => $users[2]->id,
                'shift_id' => $shifts[1]->id,
                'status' => Confirmed::class,
                'reviewed_by' => $staff->id,
            ]);
            HourLog::create([
                'user_id' => $users[3]->id,
                'shift_id' => $shifts[1]->id,
                'status' => Confirmed::class,
                'reviewed_by' => $staff->id,
            ]);
        }

        // --- CheckedIn (currently working — use the "happening now" shift) ---
        $nowShift = collect($shifts)->first(fn ($s) => $s->start_at->isPast() && $s->end_at->isFuture());
        if ($nowShift) {
            HourLog::create([
                'user_id' => $users[4]->id,
                'shift_id' => $nowShift->id,
                'status' => CheckedIn::class,
                'reviewed_by' => $staff->id,
                'started_at' => $nowShift->start_at,
            ]);
        }

        // --- Released (from various prior states) ---
        if (isset($shifts[2])) {
            // Released from Interested
            HourLog::create([
                'user_id' => $users[5]->id,
                'shift_id' => $shifts[2]->id,
                'status' => 'released',
                'reviewed_by' => $staff->id,
            ]);
        }

        // --- CheckedOut (completed, on past shifts — with tags) ---
        $pastShifts = collect($shifts)->filter(fn ($s) => $s->end_at->isPast());
        foreach ($pastShifts->take(3) as $i => $pastShift) {
            $userIndex = min($i, $users->count() - 1);

            $startedAt = $pastShift->start_at->copy()->addMinutes(fake()->numberBetween(0, 15));
            $endedAt = $pastShift->end_at->copy()->subMinutes(fake()->numberBetween(0, 10));

            $hourLog = HourLog::create([
                'user_id' => $users[$userIndex]->id,
                'shift_id' => $pastShift->id,
                'status' => 'checked_out',
                'reviewed_by' => $staff->id,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
            ]);

            // Propagate tags like checkOut service does
            $tags = collect();
            if ($pastShift->tags->isNotEmpty()) {
                $tags = $tags->merge($pastShift->tags);
            }
            $position = $pastShift->position;
            if ($position && $position->tags->isNotEmpty()) {
                $tags = $tags->merge($position->tags);
            }
            if ($tags->isNotEmpty()) {
                $hourLog->attachTags($tags);
            }
        }

        // --- A full shift (capacity 2, both slots filled and checked out) ---
        $pastShift = $pastShifts->first();
        if ($pastShift && $users->count() >= 8) {
            $startedAt = $pastShift->start_at->copy()->addMinutes(5);
            $endedAt = $pastShift->end_at->copy()->subMinutes(5);

            $hourLog = HourLog::create([
                'user_id' => $users[7]->id,
                'shift_id' => $pastShift->id,
                'status' => 'checked_out',
                'reviewed_by' => $staff->id,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
            ]);
            if ($pastShift->position?->tags->isNotEmpty()) {
                $hourLog->attachTags($pastShift->position->tags);
            }
        }
    }

    /**
     * Create hour logs covering every self-reported lifecycle state.
     */
    private function seedSelfReportedLifecycle(array $positions, $users, User $staff): void
    {
        $grantWriter = $positions[4]; // Grant Writer
        $socialMedia = $positions[6]; // Social Media
        $posterDesigner = $positions[7]; // Poster Designer

        // --- Pending (waiting for review) ---
        HourLog::create([
            'user_id' => $users[0]->id,
            'position_id' => $grantWriter->id,
            'status' => Pending::class,
            'started_at' => now()->subDays(2)->setHour(10),
            'ended_at' => now()->subDays(2)->setHour(13),
            'notes' => 'Worked on the Oregon Arts Commission spring grant application.',
        ]);

        HourLog::create([
            'user_id' => $users[1]->id,
            'position_id' => $socialMedia->id,
            'status' => Pending::class,
            'started_at' => now()->subDay()->setHour(14),
            'ended_at' => now()->subDay()->setHour(16),
            'notes' => 'Created Instagram and Facebook posts for the upcoming show series.',
        ]);

        HourLog::create([
            'user_id' => $users[3]->id,
            'position_id' => $posterDesigner->id,
            'status' => Pending::class,
            'started_at' => now()->subDays(3)->setHour(19),
            'ended_at' => now()->subDays(3)->setHour(22),
            'notes' => 'Designed poster for the May showcase.',
        ]);

        // --- Approved (with tags propagated) ---
        $approvedLog = HourLog::create([
            'user_id' => $users[2]->id,
            'position_id' => $grantWriter->id,
            'status' => 'approved',
            'reviewed_by' => $staff->id,
            'started_at' => now()->subWeeks(2)->setHour(9),
            'ended_at' => now()->subWeeks(2)->setHour(14),
        ]);
        $approvedLog->attachTags(array_merge(
            $grantWriter->tags->pluck('name')->toArray(),
            ['spring-grant-2026']
        ));

        $approvedLog2 = HourLog::create([
            'user_id' => $users[4]->id,
            'position_id' => $socialMedia->id,
            'status' => 'approved',
            'reviewed_by' => $staff->id,
            'started_at' => now()->subWeeks(1)->setHour(10),
            'ended_at' => now()->subWeeks(1)->setHour(12),
        ]);
        $approvedLog2->attachTags($socialMedia->tags->pluck('name')->toArray());

        // Multiple approved logs for one user (shows cumulative hours)
        for ($i = 0; $i < 3; $i++) {
            $log = HourLog::create([
                'user_id' => $users[5]->id,
                'position_id' => $grantWriter->id,
                'status' => 'approved',
                'reviewed_by' => $staff->id,
                'started_at' => now()->subWeeks($i + 3)->setHour(10),
                'ended_at' => now()->subWeeks($i + 3)->setHour(13),
            ]);
            $log->attachTags($grantWriter->tags->pluck('name')->toArray());
        }

        // --- Rejected (with reviewer notes) ---
        HourLog::create([
            'user_id' => $users[6]->id,
            'position_id' => $grantWriter->id,
            'status' => 'rejected',
            'reviewed_by' => $staff->id,
            'started_at' => now()->subWeeks(1)->setHour(10),
            'ended_at' => now()->subWeeks(1)->setHour(11),
            'notes' => 'Please resubmit — the grant writing session was for a different organization, not CMC.',
        ]);
    }

    /**
     * A completed event with a full volunteer roster showing the
     * entire lifecycle: sign-up → confirm → check-in → check-out.
     */
    private function seedCompletedEventScenario(array $positions, $events, $users, User $staff): void
    {
        $pastEvent = $events->skip(5)->first();
        if (! $pastEvent) {
            return;
        }

        $startAt = now()->subDays(10)->setHour(19);
        $endAt = (clone $startAt)->addHours(5);

        // Sound Engineer shift — 2 volunteers checked out
        $soundShift = Shift::create([
            'position_id' => $positions[0]->id,
            'event_id' => $pastEvent->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'capacity' => 2,
        ]);

        foreach ([0, 1] as $i) {
            $checkedInAt = $startAt->copy()->addMinutes(fake()->numberBetween(0, 10));
            $checkedOutAt = $endAt->copy()->subMinutes(fake()->numberBetween(0, 15));
            $log = HourLog::create([
                'user_id' => $users[$i]->id,
                'shift_id' => $soundShift->id,
                'status' => 'checked_out',
                'reviewed_by' => $staff->id,
                'started_at' => $checkedInAt,
                'ended_at' => $checkedOutAt,
            ]);
            $log->attachTags(array_merge(
                $positions[0]->tags->pluck('name')->toArray(),
                ['completed-show']
            ));
        }

        // Door Person shift — 1 checked out, 1 released mid-event
        $doorShift = Shift::create([
            'position_id' => $positions[1]->id,
            'event_id' => $pastEvent->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'capacity' => 2,
        ]);

        $log = HourLog::create([
            'user_id' => $users[2]->id,
            'shift_id' => $doorShift->id,
            'status' => 'checked_out',
            'reviewed_by' => $staff->id,
            'started_at' => $startAt->copy()->addMinutes(2),
            'ended_at' => $endAt->copy()->subMinutes(5),
        ]);
        $log->attachTags($positions[1]->tags->pluck('name')->toArray());

        HourLog::create([
            'user_id' => $users[3]->id,
            'shift_id' => $doorShift->id,
            'status' => 'released',
            'reviewed_by' => $staff->id,
        ]);

        // Bartender shift — walk-in (direct to checked_out for past)
        $barShift = Shift::create([
            'position_id' => $positions[2]->id,
            'event_id' => $pastEvent->id,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'capacity' => 2,
        ]);

        $log = HourLog::create([
            'user_id' => $users[4]->id,
            'shift_id' => $barShift->id,
            'status' => 'checked_out',
            'reviewed_by' => $staff->id,
            'started_at' => $startAt->copy()->addMinutes(20), // walked in late
            'ended_at' => $endAt->copy(),
        ]);
        $log->attachTags($positions[2]->tags->pluck('name')->toArray());

        // Load-In shift — all checked out
        $loadInShift = Shift::create([
            'position_id' => $positions[3]->id,
            'event_id' => $pastEvent->id,
            'start_at' => $startAt->copy()->subHours(2), // load-in starts earlier
            'end_at' => $startAt,
            'capacity' => 3,
        ]);

        foreach ([5, 6, 7] as $idx) {
            if (! isset($users[$idx])) {
                continue;
            }
            $log = HourLog::create([
                'user_id' => $users[$idx]->id,
                'shift_id' => $loadInShift->id,
                'status' => 'checked_out',
                'reviewed_by' => $staff->id,
                'started_at' => $loadInShift->start_at->copy()->addMinutes(fake()->numberBetween(0, 10)),
                'ended_at' => $loadInShift->end_at,
            ]);
            $log->attachTags($positions[3]->tags->pluck('name')->toArray());
        }
    }
}
