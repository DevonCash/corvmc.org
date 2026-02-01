<?php

namespace App\Filament\Staff\Resources\Events\Pages;

use App\Actions\Events\SyncEventSpaceReservation;
use App\Filament\Staff\Resources\Events\Actions\CancelEventAction;
use App\Filament\Staff\Resources\Events\Actions\PublishEventAction;
use App\Filament\Staff\Resources\Events\Actions\RescheduleEventAction;
use App\Filament\Staff\Resources\Events\EventResource;
use CorvMC\Events\Actions\DeleteEvent as DeleteEventAction;
use CorvMC\Events\Actions\UpdateEvent as UpdateEventAction;
use CorvMC\Events\Models\Event;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    public function isReadOnly(): bool
    {
        /** @var Event $event */
        $event = $this->record;

        return ! $event->status->isActive();
    }

    public function form(Schema $schema): Schema
    {
        return parent::form($schema)
            ->disabled($this->isReadOnly());
    }

    protected function getFormActions(): array
    {
        if ($this->isReadOnly()) {
            return [];
        }

        return parent::getFormActions();
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }

    public function getSubheading(): ?string
    {
        if ($this->isReadOnly()) {
            /** @var Event $event */
            $event = $this->record;
            $status = $event->status->getLabel();

            return "This event is {$status} and cannot be edited.";
        }

        return null;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Event $event */
        $event = $this->record;

        // Convert datetime fields to time-only for TimePicker fields
        if ($event->end_datetime) {
            $data['end_time'] = $event->end_datetime->format('H:i');
        }
        if ($event->doors_datetime) {
            $data['doors_time'] = $event->doors_datetime->format('H:i');
        }

        if ($event->usesPracticeSpace() && $event->spaceReservation) {
            $reservation = $event->spaceReservation;

            $setupMinutes = $event->start_datetime->diffInMinutes($reservation->reserved_at);
            $teardownMinutes = $reservation->reserved_until->diffInMinutes($event->end_datetime ?? $event->start_datetime->copy()->addHours(3));

            $data['setup_minutes'] = $setupMinutes;
            $data['teardown_minutes'] = $teardownMinutes;
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $setupMinutes = isset($data['setup_minutes']) && $data['setup_minutes'] !== ''
            ? (int) $data['setup_minutes']
            : null;
        $teardownMinutes = isset($data['teardown_minutes']) && $data['teardown_minutes'] !== ''
            ? (int) $data['teardown_minutes']
            : null;

        unset($data['setup_minutes'], $data['teardown_minutes']);

        /** @var Event $event */
        $event = UpdateEventAction::run($record, $data);

        $this->syncSpaceReservationIfNeeded($event, $setupMinutes, $teardownMinutes);

        return $event;
    }

    protected function syncSpaceReservationIfNeeded(Event $event, ?int $setupMinutes, ?int $teardownMinutes): void
    {
        if (! $event->usesPracticeSpace()) {
            return;
        }

        $result = SyncEventSpaceReservation::run($event, $setupMinutes, $teardownMinutes);

        if (! $result['success']) {
            $this->notifyConflicts($result['conflicts']);
        }
    }

    protected function notifyConflicts(array $conflicts): void
    {
        $messages = [];

        foreach ($conflicts['reservations'] as $reservation) {
            $time = $reservation->reserved_at->format('g:i A') . ' - ' . $reservation->reserved_until->format('g:i A');
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
            ->body("The event was saved but the space reservation could not be updated due to conflicts:\n" . implode("\n", $messages))
            ->warning()
            ->persistent()
            ->send();
    }

    protected function handleRecordDeletion(Model $record): void
    {
        DeleteEventAction::run($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label('Preview')
                ->icon('tabler-external-link')
                ->color('gray')
                ->url(fn () => $this->record->isPublished()
                    ? route('events.show', $this->record)
                    : URL::signedRoute('events.show', $this->record, now()->addHour()))
                ->openUrlInNewTab(),
            PublishEventAction::make(),
            RescheduleEventAction::make(),
            CancelEventAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
