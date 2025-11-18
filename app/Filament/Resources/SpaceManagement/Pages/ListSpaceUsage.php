<?php

namespace App\Filament\Resources\SpaceManagement\Pages;

use App\Actions\Reservations\CreateReservation;
use App\Filament\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Resources\SpaceManagement\SpaceManagementResource;
use App\Filament\Resources\SpaceManagement\Widgets\SpaceStatsWidget;
use App\Filament\Resources\SpaceManagement\Widgets\SpaceUsageWidget;
use App\Models\Reservation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSpaceUsage extends ListRecords
{
    protected static string $resource = SpaceManagementResource::class;

    protected static ?string $title = 'Space Management';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_reservation')
                ->label('Create Reservation')
                ->icon('tabler-calendar-plus')
                ->modalWidth('lg')
                ->steps(ReservationForm::getSteps())
                ->action(function (array $data) {
                    $user = User::find($data['user_id']);

                    // reserved_at and reserved_until are already Carbon instances from ReservationForm
                    $reservedAt = $data['reserved_at'];
                    $reservedUntil = $data['reserved_until'];

                    // Use CreateReservation action to properly create reservation with notifications
                    $reservation = CreateReservation::run(
                        $user,
                        $reservedAt,
                        $reservedUntil,
                        [
                            'status' => $data['status'] ?? 'confirmed',
                            'notes' => $data['notes'] ?? null,
                            'is_recurring' => $data['is_recurring'] ?? false,
                            'payment_status' => $data['payment_status'] ?? 'unpaid',
                        ]
                    );

                    Notification::make()
                        ->title('Reservation Created')
                        ->body('The reservation has been created successfully.')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SpaceStatsWidget::class,
            SpaceUsageWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'upcoming' => Tab::make('Upcoming')
                ->icon('tabler-calendar-clock')
                ->badge(function () {
                    return Reservation::where('reserved_at', '>=', now()->startOfDay())
                        ->where('status', '!=', 'cancelled')
                        ->count();
                })
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('reserved_at', '>=', now()->startOfDay())
                    ->where('status', '!=', 'cancelled')
                    ->orderBy('reserved_at', 'asc')),

            'needs_attention' => Tab::make('Needs Attention')
                ->icon('tabler-alert-circle')
                ->badge(fn () => Reservation::needsAttention()->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    /** @phpstan-ignore method.notFound */
                    ->needsAttention()
                    ->orderBy('reserved_at', 'asc')),

            'all' => Tab::make('All')
                ->icon('tabler-calendar')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->orderBy('reserved_at', 'desc')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'upcoming';
    }
}
