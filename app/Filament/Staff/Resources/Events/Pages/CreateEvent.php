<?php

namespace App\Filament\Staff\Resources\Events\Pages;

use App\Actions\Events\SyncEventSpaceReservation;
use App\Filament\Staff\Resources\Events\EventResource;
use App\Filament\Staff\Resources\Events\Schemas\EventCreateWizard;
use Carbon\Carbon;
use CorvMC\Events\Actions\CreateEvent as CreateEventAction;
use CorvMC\Events\Models\Event;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    public function form(Schema $schema): Schema
    {
        return EventCreateWizard::configure($schema);
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Extract wizard-specific fields
        $setupMinutes = isset($data['setup_minutes']) && $data['setup_minutes'] !== ''
            ? (int) $data['setup_minutes']
            : null;
        $teardownMinutes = isset($data['teardown_minutes']) && $data['teardown_minutes'] !== ''
            ? (int) $data['teardown_minutes']
            : null;
        $conflictStatus = $data['conflict_status'] ?? 'available';
        $forceOverride = (bool) ($data['force_override'] ?? false);

        // Remove wizard-only fields from event data
        unset(
            $data['setup_minutes'],
            $data['teardown_minutes'],
            $data['conflict_status'],
            $data['conflict_data'],
            $data['force_override'],
            $data['conflict_status_display']
        );

        // Build end_datetime from start_datetime date + end_time
        if (! empty($data['end_time']) && ! empty($data['start_datetime'])) {
            $startDatetime = $data['start_datetime'] instanceof Carbon
                ? $data['start_datetime']
                : Carbon::parse($data['start_datetime'], config('app.timezone'));
            $endDatetime = Carbon::parse(
                $startDatetime->toDateString().' '.$data['end_time'],
                config('app.timezone')
            );
            $data['end_datetime'] = $endDatetime;
        }
        unset($data['end_time']);

        // Create the event
        $event = CreateEventAction::run($data);

        // Sync space reservation if needed
        $this->syncSpaceReservationIfNeeded(
            $event,
            $setupMinutes,
            $teardownMinutes,
            $conflictStatus,
            $forceOverride
        );

        return $event;
    }

    protected function syncSpaceReservationIfNeeded(
        Event $event,
        ?int $setupMinutes,
        ?int $teardownMinutes,
        string $conflictStatus,
        bool $forceOverride
    ): void {
        // Skip if not using CMC practice space
        if (! $event->usesPracticeSpace()) {
            return;
        }

        // Determine if we should create the reservation
        $shouldCreate = match ($conflictStatus) {
            'available' => true,
            'setup_conflict' => true, // Create with potentially reduced buffer
            'event_conflict' => $forceOverride, // Only create if admin override
            default => true,
        };

        if (! $shouldCreate) {
            Notification::make()
                ->title('Space reservation not created')
                ->body('The event was created but no space reservation was made due to conflicts.')
                ->warning()
                ->send();

            return;
        }

        $result = SyncEventSpaceReservation::run(
            $event,
            $setupMinutes,
            $teardownMinutes,
            $forceOverride
        );

        if (! $result['success']) {
            $this->notifyConflicts($result['conflicts']);
        } elseif ($forceOverride && ($conflictStatus === 'event_conflict' || $conflictStatus === 'setup_conflict')) {
            Notification::make()
                ->title('Space reservation created with override')
                ->body('The space reservation was created, overriding existing conflicts.')
                ->warning()
                ->send();
        } else {
            Notification::make()
                ->title('Space reservation created')
                ->body('The practice space has been reserved for this event.')
                ->success()
                ->send();
        }
    }

    protected function notifyConflicts(array $conflicts): void
    {
        $messages = [];

        foreach ($conflicts['reservations'] as $reservation) {
            $time = $reservation->reserved_at->format('g:i A').' - '.$reservation->reserved_until->format('g:i A');
            $messages[] = "Reservation: {$time}";
        }

        foreach ($conflicts['productions'] as $production) {
            $messages[] = "Production: {$production->title}";
        }

        foreach ($conflicts['closures'] as $closure) {
            $messages[] = "Closure: {$closure->type->getLabel()}";
        }

        Notification::make()
            ->title('Space reservation conflicts detected')
            ->body("The event was created but the space reservation could not be made due to conflicts:\n".implode("\n", $messages))
            ->warning()
            ->persistent()
            ->send();
    }
}
