<?php

namespace App\Filament\Staff\Resources\Events\Actions;

use App\Filament\Staff\Resources\Events\EventResource;
use CorvMC\Events\Actions\RescheduleEvent;
use CorvMC\Events\Enums\EventStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;

class RescheduleEventAction
{
    public static function make(): Action
    {
        return Action::make('reschedule')
            ->label('Reschedule Event')
            ->icon('tabler-calendar-event')
            ->color('warning')
            ->visible(fn ($record) => $record->status === EventStatus::Scheduled)
            ->authorize('reschedule')
            ->schema([
                Toggle::make('has_new_date')
                    ->label('New date is known')
                    ->default(false)
                    ->live()
                    ->helperText('If unchecked, the event will be marked as "Postponed (TBA)".'),

                DateTimePicker::make('start_datetime')
                    ->label('New Start Date & Time')
                    ->required()
                    ->visible(fn ($get) => $get('has_new_date'))
                    ->native(false)
                    ->seconds(false),

                DateTimePicker::make('end_datetime')
                    ->label('New End Date & Time')
                    ->visible(fn ($get) => $get('has_new_date'))
                    ->native(false)
                    ->seconds(false)
                    ->after('start_datetime'),

                Textarea::make('reason')
                    ->label('Reason for Rescheduling')
                    ->placeholder('Why is this event being rescheduled?')
                    ->helperText('This will be stored for reference.'),
            ])
            ->modalHeading('Reschedule Event')
            ->modalDescription('Reschedule this event to a new date or mark it as postponed (TBA).')
            ->modalSubmitActionLabel('Reschedule')
            ->action(function ($record, array $data) {
                $newEventData = [];

                if ($data['has_new_date'] ?? false) {
                    $newEventData['start_datetime'] = $data['start_datetime'];
                    if (! empty($data['end_datetime'])) {
                        $newEventData['end_datetime'] = $data['end_datetime'];
                    }
                }

                $result = RescheduleEvent::run($record, $newEventData, $data['reason'] ?? null);

                // If a new event was created, redirect to it
                if (($data['has_new_date'] ?? false) && $result->id !== $record->id) {
                    Notification::make()
                        ->title('Event Rescheduled')
                        ->body("A new event has been created for the new date.")
                        ->success()
                        ->send();

                    return redirect(EventResource::getUrl('edit', ['record' => $result]));
                }

                // TBA mode - stayed on same event
                Notification::make()
                    ->title('Event Postponed')
                    ->body("'{$record->title}' has been marked as postponed (TBA).")
                    ->warning()
                    ->send();
            });
    }
}
