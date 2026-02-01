<?php

namespace App\Filament\Staff\Resources\SpaceClosures\Schemas;

use Carbon\Carbon;
use CorvMC\SpaceManagement\Actions\Reservations\GetReservationsAffectedByClosure;
use CorvMC\SpaceManagement\Enums\ClosureType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;

class SpaceClosureCreateWizard
{
    public static function getSteps(): array
    {
        return [
            Wizard\Step::make('Closure Details')
                ->icon('tabler-calendar-off')
                ->schema(static::detailsStep())
                ->afterValidation(fn (Get $get, callable $set) => static::checkAffectedReservations($get, $set)),

            Wizard\Step::make('Affected Reservations')
                ->icon('tabler-calendar-x')
                ->schema(static::affectedReservationsStep()),

            Wizard\Step::make('Confirm')
                ->icon('tabler-circle-check')
                ->schema(static::confirmationStep()),
        ];
    }

    protected static function detailsStep(): array
    {
        return [
            Select::make('type')
                ->label('Closure Type')
                ->options(ClosureType::class)
                ->required()
                ->default(ClosureType::Other)
                ->columnSpanFull(),

            Grid::make(2)
                ->schema([
                    DateTimePicker::make('starts_at')
                        ->label('Starts At')
                        ->required()
                        ->native(true)
                        ->seconds(false),

                    DateTimePicker::make('ends_at')
                        ->label('Ends At')
                        ->required()
                        ->native(true)
                        ->seconds(false)
                        ->after('starts_at'),
                ]),

            Textarea::make('notes')
                ->label('Notes')
                ->rows(3)
                ->placeholder('Additional details about the closure...')
                ->columnSpanFull(),

            // Hidden fields for affected reservations data
            Hidden::make('affected_reservations_data'),
        ];
    }

    protected static function affectedReservationsStep(): array
    {
        return [
            ViewField::make('affected_reservations_preview')
                ->view('filament.components.closure-affected-reservations'),

            Section::make('Cancel Reservations')
                ->compact()
                ->visible(fn (Get $get) => static::hasAffectedReservations($get))
                ->schema([
                    Toggle::make('cancel_affected_reservations')
                        ->label('Cancel all affected reservations')
                        ->helperText('Members will be notified with the closure type as the cancellation reason.')
                        ->default(false),
                ]),

            Placeholder::make('no_reservations_note')
                ->content('No reservations will be affected by this closure. You can proceed.')
                ->visible(fn (Get $get) => ! static::hasAffectedReservations($get)),
        ];
    }

    protected static function confirmationStep(): array
    {
        return [
            Section::make('Closure Summary')
                ->compact()
                ->schema([
                    Placeholder::make('summary_type')
                        ->label('Type')
                        ->content(function (Get $get) {
                            $type = $get('type');
                            if ($type instanceof ClosureType) {
                                return $type->getLabel();
                            }

                            return ClosureType::tryFrom($type)?->getLabel() ?? $type;
                        }),

                    Placeholder::make('summary_period')
                        ->label('Period')
                        ->content(function (Get $get) {
                            $startsAt = $get('starts_at');
                            $endsAt = $get('ends_at');

                            if (! $startsAt || ! $endsAt) {
                                return 'Not set';
                            }

                            $start = $startsAt instanceof Carbon ? $startsAt : Carbon::parse($startsAt);
                            $end = $endsAt instanceof Carbon ? $endsAt : Carbon::parse($endsAt);

                            if ($start->isSameDay($end)) {
                                return $start->format('l, F j, Y').' from '.$start->format('g:i A').' to '.$end->format('g:i A');
                            }

                            return $start->format('M j, Y g:i A').' to '.$end->format('M j, Y g:i A');
                        }),

                    Placeholder::make('summary_notes')
                        ->label('Notes')
                        ->visible(fn (Get $get) => filled($get('notes')))
                        ->content(fn (Get $get) => $get('notes')),
                ]),

            Section::make('Reservations')
                ->compact()
                ->schema([
                    Placeholder::make('reservations_action')
                        ->label('Action')
                        ->content(function (Get $get) {
                            $hasAffected = static::hasAffectedReservations($get);
                            $willCancel = $get('cancel_affected_reservations');

                            if (! $hasAffected) {
                                return '✓ No reservations affected';
                            }

                            $count = static::getAffectedCount($get);

                            if ($willCancel) {
                                return "⚠ {$count} ".str('reservation')->plural($count).' will be cancelled and members notified';
                            }

                            return "⚠ {$count} ".str('reservation')->plural($count).' will remain (not cancelled)';
                        }),
                ]),
        ];
    }

    protected static function checkAffectedReservations(Get $get, callable $set): void
    {
        $startsAt = $get('starts_at');
        $endsAt = $get('ends_at');

        if (! $startsAt || ! $endsAt) {
            $set('affected_reservations_preview', null);
            $set('affected_reservations_data', null);

            return;
        }

        $startsAtCarbon = $startsAt instanceof Carbon
            ? $startsAt
            : Carbon::parse($startsAt, config('app.timezone'));
        $endsAtCarbon = $endsAt instanceof Carbon
            ? $endsAt
            : Carbon::parse($endsAt, config('app.timezone'));

        if ($endsAtCarbon <= $startsAtCarbon) {
            $set('affected_reservations_preview', null);
            $set('affected_reservations_data', null);

            return;
        }

        $reservations = GetReservationsAffectedByClosure::run($startsAtCarbon, $endsAtCarbon);

        $formatted = $reservations->map(function ($r) {
            try {
                $userName = $r->getDisplayTitle();
            } catch (\RuntimeException) {
                $userName = $r->reservable?->name ?? 'Unknown';
            }

            return [
                'id' => $r->id,
                'user_name' => $userName,
                'date' => $r->reserved_at->format('D, M j'),
                'time_range' => $r->reserved_at->format('g:i A').' - '.$r->reserved_until->format('g:i A'),
                'status' => $r->status->getLabel(),
            ];
        })->values()->all();

        $set('affected_reservations_preview', json_encode($formatted));
        $set('affected_reservations_data', json_encode($reservations->pluck('id')->all()));
    }

    protected static function hasAffectedReservations(Get $get): bool
    {
        $data = $get('affected_reservations_data');
        if (! $data) {
            return false;
        }

        $ids = json_decode($data, true);

        return is_array($ids) && count($ids) > 0;
    }

    protected static function getAffectedCount(Get $get): int
    {
        $data = $get('affected_reservations_data');
        if (! $data) {
            return 0;
        }

        $ids = json_decode($data, true);

        return is_array($ids) ? count($ids) : 0;
    }
}
