<?php

namespace App\Filament\Resources\Reservations\Pages;

use App\Filament\Resources\Reservations\ReservationResource;
use App\Filament\Resources\Reservations\Widgets\ReservationStatsOverview;
use App\Models\User;
use Filament\Actions\CreateAction;
use App\Filament\Resources\Reservations\Schemas\ReservationForm;
use App\Models\RehearsalReservation;
use Filament\Actions\Action;
use Filament\Forms\Components\Wizard\Step;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReservations extends ListRecords
{
    protected static string $resource = ReservationResource::class;

    protected static ?string $title = 'Reserve Practice Space';

    protected function getHeaderWidgets(): array
    {
        return [
            // ReservationStatsOverview::class, // Disabled - simplifying member panel
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_reservation')
                ->label('Reserve Space')
                ->icon('tabler-calendar-plus')
                ->modalWidth('lg')
                ->steps(ReservationForm::getSteps())
                ->action(function (array $data) {
                    $user = User::find($data['user_id']);

                    // reserved_at and reserved_until are already Carbon instances from ReservationForm
                    $reservedAt = $data['reserved_at'];
                    $reservedUntil = $data['reserved_until'];

                    // Use CreateReservation action to properly create reservation with notifications
                    $reservation = \App\Actions\Reservations\CreateReservation::run(
                        $user,
                        $reservedAt,
                        $reservedUntil,
                        [
                            'status' => $data['status'],
                            'notes' => $data['notes'] ?? null,
                            'is_recurring' => $data['is_recurring'] ?? false,
                        ]
                    );

                    Notification::make()
                        ->title('Reservation Created')
                        ->body('Your reservation has been created successfully.')
                        ->success()
                        ->send();
                }),
        ];
    }


    public function getTabs(): array
    {
        return [
            'upcoming' => Tab::make('Upcoming')
                ->icon('tabler-calendar-clock')
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->where('status', '!=', 'cancelled')
                    ->where('reserved_at', '>', now())),

            'all' => Tab::make('All')
                ->icon('tabler-calendar'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'upcoming';
    }

    /**
     * Scope reservations to current user only
     */
    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->where('reservable_type', \App\Models\User::class)
            ->where('reservable_id', auth()->id());
    }
}
