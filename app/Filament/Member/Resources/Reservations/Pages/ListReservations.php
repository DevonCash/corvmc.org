<?php

namespace App\Filament\Member\Resources\Reservations\Pages;

use App\Filament\Member\Resources\Reservations\ReservationResource;
use App\Filament\Member\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Member\Resources\Reservations\Widgets\RecurringSeriesTableWidget;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListReservations extends ListRecords
{
    protected static string $resource = ReservationResource::class;

    protected static ?string $title = 'Reserve Practice Space';

    protected string $view = 'space-management::filament.pages.list-reservations';

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getFooterWidgets(): array
    {
        return [
            RecurringSeriesTableWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'upcoming' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->upcoming()),
            'all' => Tab::make(),
        ];
    }

    protected function getReserveSpaceAction(): Action
    {
        return Action::make('create_reservation')
            ->label('Reserve Space')
            ->icon('tabler-calendar-plus')
            ->modalWidth('lg')
            ->steps(ReservationForm::getSteps())
            ->action(function (array $data) {
                $user = User::find($data['user_id']);

                // Parse datetime strings to Carbon instances
                $reservedAt = $data['reserved_at'] instanceof Carbon
                    ? $data['reserved_at']
                    : Carbon::parse($data['reserved_at']);
                $reservedUntil = $data['reserved_until'] instanceof Carbon
                    ? $data['reserved_until']
                    : Carbon::parse($data['reserved_until']);

                // Use CreateReservation action to properly create reservation with notifications
                $reservation = \CorvMC\SpaceManagement\Actions\Reservations\CreateReservation::run(
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
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getReserveSpaceAction(),
        ];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->emptyStateActions([
                $this->getReserveSpaceAction(),
            ]);
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'upcoming';
    }
}
