<?php

namespace App\Filament\Staff\Resources\Events\Pages;

use App\Actions\Events\SyncEventSpaceReservation;
use App\Filament\Staff\Resources\Events\EventResource;
use App\Filament\Staff\Resources\Events\Schemas\EventCreateWizard;
use App\Filament\Staff\Resources\Venues\VenueResource;
use Carbon\Carbon;
use CorvMC\Events\Actions\CreateEvent as CreateEventAction;
use CorvMC\Events\Exceptions\SchedulingConflictException;
use CorvMC\Events\Models\Event;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()->with('organizer');
    }

    public function getTabs(): array
    {
        $activeStatuses = ['scheduled', 'at_capacity'];

        return [
            'upcoming' => Tab::make('Upcoming')
                ->icon('tabler-calendar-event')
                ->badge(fn () => Event::where('start_datetime', '>=', now()->startOfDay())
                    ->whereIn('status', $activeStatuses)
                    ->count())
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('start_datetime', '>=', now()->startOfDay())
                    ->whereIn('status', $activeStatuses)
                    ->orderBy('start_datetime', 'asc')),

            'past' => Tab::make('Past')
                ->icon('tabler-history')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('start_datetime', '<', now()->startOfDay())
                    ->orderBy('start_datetime', 'desc')),

            'all' => Tab::make('All')
                ->icon('tabler-list')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->orderBy('start_datetime', 'desc')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'upcoming';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('venues')
                ->label('Venues')
                ->icon('tabler-map-pin')
                ->color('gray')
                ->url(VenueResource::getUrl('index')),

            Action::make('create_event')
                ->label('New Event')
                ->icon('tabler-plus')
                ->modalWidth('lg')
                ->steps(EventCreateWizard::getSteps())
                ->action(function (array $data) {
                    $this->createEvent($data);
                }),
        ];
    }

    protected function createEvent(array $data): void
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

        // When force override is enabled for conflicts, create event without end_datetime
        // to bypass the CheckEventSpaceConflicts listener, then update with end time
        $endDateTime = $data['end_datetime'] ?? null;
        $bypassConflictCheck = $forceOverride && $conflictStatus === 'event_conflict';

        if ($bypassConflictCheck && $endDateTime) {
            unset($data['end_datetime']);
        }

        try {
            $event = CreateEventAction::run($data);

            // If we bypassed conflict check, update with end_datetime now
            if ($bypassConflictCheck && $endDateTime) {
                $event->update(['end_datetime' => $endDateTime]);
            }
        } catch (SchedulingConflictException $e) {
            Notification::make()
                ->title('Event creation failed')
                ->body($e->getMessage().'. Please go back and resolve conflicts or use admin override.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        // Sync space reservation if needed
        $this->syncSpaceReservationIfNeeded(
            $event,
            $setupMinutes,
            $teardownMinutes,
            $conflictStatus,
            $forceOverride
        );

        Notification::make()
            ->title('Event Created')
            ->body("Event \"{$event->title}\" has been created.")
            ->success()
            ->send();

        // Redirect to edit page
        $this->redirect(EventResource::getUrl('edit', ['record' => $event]));
    }

    protected function syncSpaceReservationIfNeeded(
        \CorvMC\Events\Models\Event $event,
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
            'setup_conflict' => true,
            'event_conflict' => $forceOverride,
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
            $messages = [];
            foreach ($result['conflicts']['reservations'] as $reservation) {
                $time = $reservation->reserved_at->format('g:i A').' - '.$reservation->reserved_until->format('g:i A');
                $name = $reservation->getDisplayTitle();
                $messages[] = "Reservation: {$name} ({$time})";
            }
            foreach ($result['conflicts']['productions'] as $production) {
                $messages[] = "Event: {$production->title}";
            }
            foreach ($result['conflicts']['closures'] as $closure) {
                $messages[] = "Closure: {$closure->type->getLabel()}";
            }

            Notification::make()
                ->title('Space reservation conflicts detected')
                ->body("The event was created but the space reservation could not be made:\n".implode("\n", $messages))
                ->warning()
                ->persistent()
                ->send();
        } elseif ($forceOverride && ($conflictStatus === 'event_conflict' || $conflictStatus === 'setup_conflict')) {
            Notification::make()
                ->title('Space reservation created with override')
                ->body('The space reservation was created, overriding existing conflicts.')
                ->warning()
                ->send();
        }
    }
}
