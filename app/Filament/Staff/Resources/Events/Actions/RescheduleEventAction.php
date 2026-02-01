<?php

namespace App\Filament\Staff\Resources\Events\Actions;

use App\Actions\Events\CheckEventConflicts;
use App\Actions\Events\SyncEventSpaceReservation;
use App\Filament\Staff\Resources\Events\EventResource;
use App\Settings\ReservationSettings;
use Carbon\Carbon;
use CorvMC\Events\Actions\RescheduleEvent;
use CorvMC\Events\Enums\EventStatus;
use CorvMC\Events\Models\Event;
use CorvMC\Events\Models\Venue;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;

class RescheduleEventAction
{
    public static function make(): Action
    {
        return Action::make('reschedule')
            ->label('Reschedule')
            ->icon('tabler-calendar-event')
            ->color('warning')
            ->visible(fn ($record) => in_array($record->status, [EventStatus::Scheduled, EventStatus::Cancelled]))
            ->authorize('reschedule')
            ->modalWidth('lg')
            ->steps(static::getSteps())
            ->fillForm(fn (Event $record) => [
                'venue_id' => $record->venue_id,
            ])
            ->modalHeading('Reschedule')
            ->modalSubmitActionLabel('Reschedule')
            ->action(function ($record, array $data) {
                static::handleReschedule($record, $data);
            });
    }

    public static function getSteps(): array
    {
        return [
            Wizard\Step::make('New Date')
                ->icon('tabler-calendar-event')
                ->schema(static::dateStep())
                ->afterValidation(fn (Get $get, callable $set) => static::checkConflicts($get, $set)),

            Wizard\Step::make('Space Reservation')
                ->icon('tabler-home')
                ->visible(fn (Get $get) => $get('has_new_date') && static::isCmcVenue($get))
                ->schema(static::spaceReservationStep()),

            Wizard\Step::make('Confirm')
                ->icon('tabler-circle-check')
                ->schema(static::confirmationStep()),
        ];
    }

    protected static function dateStep(): array
    {
        return [
            Toggle::make('has_new_date')
                ->label('New date is known')
                ->default(false)
                ->live()
                ->helperText('If unchecked, the event will be marked as "Postponed (TBA)".'),

            Grid::make(2)
                ->visible(fn (Get $get) => $get('has_new_date'))
                ->schema([
                    DateTimePicker::make('start_datetime')
                        ->label('New Start Date & Time')
                        ->required()
                        ->native(true)
                        ->seconds(false)
                        ->live()
                        ->afterStateUpdated(fn (callable $set) => $set('end_time', null)),

                    TimePicker::make('end_time')
                        ->label('End Time')
                        ->seconds(false)
                        ->live()
                        ->required(fn (Get $get) => static::isCmcVenue($get))
                        ->helperText(fn (Get $get) => static::isCmcVenue($get)
                            ? 'Required for CMC events to check space availability'
                            : 'Optional for external venues'),
                ]),

            Textarea::make('reason')
                ->label('Reason for Rescheduling')
                ->placeholder('Why is this event being rescheduled?')
                ->helperText('This will be stored for reference.')
                ->columnSpanFull(),

            // Hidden fields for conflict data
            Hidden::make('conflict_status'),
            Hidden::make('conflict_data'),
            Hidden::make('setup_minutes'),
            Hidden::make('teardown_minutes'),
            Hidden::make('venue_id'),
        ];
    }

    protected static function spaceReservationStep(): array
    {
        $settings = app(ReservationSettings::class);

        return [
            ViewField::make('conflict_status_display')
                ->view('filament.components.event-conflict-status'),

            Section::make('Setup & Teardown')
                ->compact()
                ->description('Adjust the time blocked before and after your event.')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('setup_minutes')
                                ->label('Setup Time')
                                ->helperText('Minutes before event start')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(480)
                                ->default($settings->default_event_setup_minutes)
                                ->suffix('minutes')
                                ->live()
                                ->afterStateUpdated(fn (Get $get, callable $set) => static::recheckConflicts($get, $set)),

                            TextInput::make('teardown_minutes')
                                ->label('Teardown Time')
                                ->helperText('Minutes after event end')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(480)
                                ->default($settings->default_event_teardown_minutes)
                                ->suffix('minutes')
                                ->live()
                                ->afterStateUpdated(fn (Get $get, callable $set) => static::recheckConflicts($get, $set)),
                        ]),
                ]),

            Section::make('Admin Override')
                ->compact()
                ->visible(fn () => auth()->user()?->hasRole('admin'))
                ->schema([
                    Toggle::make('force_override')
                        ->label('Override conflicts')
                        ->helperText('Force the reservation even if it conflicts with other bookings.')
                        ->default(false),
                ]),

            Placeholder::make('conflict_warning')
                ->content('You cannot proceed with this time slot due to conflicts. Please go back and choose a different time.')
                ->visible(fn (Get $get) => $get('conflict_status') === 'event_conflict' && ! auth()->user()?->hasRole('admin'))
                ->extraAttributes(['class' => 'text-danger-600']),

            Hidden::make('force_override')->default(false),
        ];
    }

    protected static function confirmationStep(): array
    {
        return [
            Section::make('Reschedule Summary')
                ->compact()
                ->schema([
                    Placeholder::make('summary_action')
                        ->label('Action')
                        ->content(fn (Get $get) => $get('has_new_date')
                            ? 'Create new event with the new date'
                            : 'Mark as Postponed (TBA)'),

                    Placeholder::make('summary_datetime')
                        ->label('New Date & Time')
                        ->visible(fn (Get $get) => $get('has_new_date'))
                        ->content(function (Get $get) {
                            $start = $get('start_datetime');
                            $end = $get('end_time');

                            if (! $start) {
                                return 'Not set';
                            }

                            $startCarbon = $start instanceof Carbon ? $start : Carbon::parse($start);
                            $formatted = $startCarbon->format('l, F j, Y \a\t g:i A');

                            if ($end) {
                                $formatted .= ' - '.$end;
                            }

                            return $formatted;
                        }),

                    Placeholder::make('summary_reason')
                        ->label('Reason')
                        ->content(fn (Get $get) => $get('reason') ?: 'No reason provided'),
                ]),

            Section::make('Space Reservation')
                ->compact()
                ->visible(fn (Get $get) => $get('has_new_date') && static::isCmcVenue($get))
                ->schema([
                    Placeholder::make('reservation_status')
                        ->label('Status')
                        ->content(function (Get $get) {
                            $status = $get('conflict_status');
                            $forceOverride = $get('force_override');

                            return match ($status) {
                                'available' => '✓ Space is available - reservation will be created',
                                'setup_conflict' => $forceOverride
                                    ? '⚠ Setup/teardown conflicts will be overridden'
                                    : '⚠ Setup/teardown has conflicts - reservation will be created with reduced buffer',
                                'event_conflict' => $forceOverride
                                    ? '⚠ Event time conflicts will be overridden'
                                    : '✗ Event time has conflicts - reservation cannot be created',
                                default => 'Checking availability...',
                            };
                        }),
                ]),
        ];
    }

    protected static function isCmcVenue(Get $get): bool
    {
        $venueId = $get('venue_id');
        if (! $venueId) {
            return true; // Default to CMC
        }

        return Venue::find($venueId)?->is_cmc ?? false;
    }

    protected static function checkConflicts(Get $get, callable $set): void
    {
        if (! $get('has_new_date')) {
            $set('conflict_status', 'available');
            $set('conflict_data', null);

            return;
        }

        if (! static::isCmcVenue($get)) {
            $set('conflict_status', 'available');
            $set('conflict_data', null);

            return;
        }

        $startDatetime = $get('start_datetime');
        $endTime = $get('end_time');

        if (! $startDatetime || ! $endTime) {
            $set('conflict_status', null);
            $set('conflict_data', null);

            return;
        }

        $startCarbon = $startDatetime instanceof Carbon
            ? $startDatetime
            : Carbon::parse($startDatetime, config('app.timezone'));

        $endCarbon = Carbon::parse($startCarbon->toDateString().' '.$endTime, config('app.timezone'));

        $settings = app(ReservationSettings::class);
        $setupMinutes = (int) ($get('setup_minutes') ?? $settings->default_event_setup_minutes);
        $teardownMinutes = (int) ($get('teardown_minutes') ?? $settings->default_event_teardown_minutes);

        if (! $get('setup_minutes')) {
            $set('setup_minutes', $settings->default_event_setup_minutes);
        }
        if (! $get('teardown_minutes')) {
            $set('teardown_minutes', $settings->default_event_teardown_minutes);
        }

        $result = CheckEventConflicts::run($startCarbon, $endCarbon, $setupMinutes, $teardownMinutes);

        $set('conflict_status', $result['status']);
        $set('conflict_data', json_encode([
            'event_conflicts' => static::formatConflictsForStorage($result['event_conflicts']),
            'setup_conflicts' => static::formatConflictsForStorage($result['setup_conflicts']),
            'all_conflicts' => static::formatConflictsForStorage($result['all_conflicts']),
        ]));
    }

    protected static function recheckConflicts(Get $get, callable $set): void
    {
        static::checkConflicts($get, $set);
    }

    protected static function formatConflictsForStorage(array $conflicts): array
    {
        return [
            'reservations' => $conflicts['reservations']->map(fn ($r) => [
                'id' => $r->id,
                'reserved_at' => $r->reserved_at->toIso8601String(),
                'reserved_until' => $r->reserved_until->toIso8601String(),
                'type' => class_basename($r),
                'display_title' => $r->getDisplayTitle(),
            ])->values()->all(),
            'productions' => $conflicts['productions']->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'start_datetime' => $p->start_datetime->toIso8601String(),
            ])->values()->all(),
            'closures' => $conflicts['closures']->map(fn ($c) => [
                'id' => $c->id,
                'reason' => $c->type->getLabel(),
                'starts_at' => $c->starts_at->toIso8601String(),
                'ends_at' => $c->ends_at->toIso8601String(),
            ])->values()->all(),
        ];
    }

    protected static function handleReschedule(Event $record, array $data): void
    {
        $hasNewDate = $data['has_new_date'] ?? false;

        if (! $hasNewDate) {
            // TBA mode - just mark as postponed
            RescheduleEvent::run($record, [], $data['reason'] ?? null);

            Notification::make()
                ->title('Event Postponed')
                ->body("'{$record->title}' has been marked as postponed (TBA).")
                ->warning()
                ->send();

            return;
        }

        // Build new event data
        $startDatetime = $data['start_datetime'] instanceof Carbon
            ? $data['start_datetime']
            : Carbon::parse($data['start_datetime'], config('app.timezone'));

        $newEventData = [
            'start_datetime' => $startDatetime,
        ];

        if (! empty($data['end_time'])) {
            $newEventData['end_datetime'] = Carbon::parse(
                $startDatetime->toDateString().' '.$data['end_time'],
                config('app.timezone')
            );
        }

        // Extract space reservation params
        $setupMinutes = isset($data['setup_minutes']) && $data['setup_minutes'] !== ''
            ? (int) $data['setup_minutes']
            : null;
        $teardownMinutes = isset($data['teardown_minutes']) && $data['teardown_minutes'] !== ''
            ? (int) $data['teardown_minutes']
            : null;
        $conflictStatus = $data['conflict_status'] ?? 'available';
        $forceOverride = (bool) ($data['force_override'] ?? false);

        // Create new event via reschedule
        $newEvent = RescheduleEvent::run($record, $newEventData, $data['reason'] ?? null);

        // Sync space reservation for new event
        if ($newEvent->usesPracticeSpace()) {
            $shouldCreate = match ($conflictStatus) {
                'available' => true,
                'setup_conflict' => true,
                'event_conflict' => $forceOverride,
                default => true,
            };

            if ($shouldCreate) {
                $result = SyncEventSpaceReservation::run(
                    $newEvent,
                    $setupMinutes,
                    $teardownMinutes,
                    $forceOverride
                );

                if (! $result['success']) {
                    Notification::make()
                        ->title('Space reservation conflicts')
                        ->body('The new event was created but space reservation has conflicts.')
                        ->warning()
                        ->send();
                }
            }
        }

        Notification::make()
            ->title('Event Rescheduled')
            ->body("A new event has been created for the new date.")
            ->success()
            ->send();

        redirect(EventResource::getUrl('edit', ['record' => $newEvent]));
    }
}
