<?php

namespace App\Filament\Staff\Resources\SpaceManagement\Pages;

use App\Filament\Member\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Staff\Resources\RecurringReservations\RecurringReservationResource;
use App\Filament\Staff\Resources\SpaceClosures\SpaceClosureResource;
use App\Filament\Staff\Resources\SpaceManagement\Actions\LockSetupAction;
use App\Filament\Staff\Resources\SpaceManagement\SpaceManagementResource;
use App\Filament\Staff\Resources\SpaceManagement\Widgets\SpaceStatsWidget;
use App\Filament\Staff\Resources\SpaceManagement\Widgets\UpcomingClosuresWidget;
use App\Models\User;
use Carbon\Carbon;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
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
            Action::make('space_closures')
                ->label('Space Closures')
                ->icon('tabler-calendar-off')
                ->color('gray')
                ->url(SpaceClosureResource::getUrl('index')),

            Action::make('recurring_reservations')
                ->label('Recurring Rehearsals')
                ->icon('tabler-calendar-repeat')
                ->color('gray')
                ->url(RecurringReservationResource::getUrl('index')),

            LockSetupAction::make(),

            Action::make('create_reservation')
                ->label('Create Reservation')
                ->icon('tabler-calendar-plus')
                ->modalWidth('lg')
                ->steps(ReservationForm::getStaffSteps())
                ->action(function (array $data) {
                    try {
                        $user = User::find($data['user_id']);

                        $reservedAt = Carbon::parse($data['reserved_at']);
                        $reservedUntil = Carbon::parse($data['reserved_until']);

                        $reservation = RehearsalReservation::create([
                            'reservable_type' => 'user',
                            'reservable_id' => $user->id,
                            'reserved_at' => $reservedAt,
                            'reserved_until' => $reservedUntil,
                            'status' => $data['status'] ?? 'confirmed',
                            'notes' => $data['notes'] ?? null,
                            'is_recurring' => $data['is_recurring'] ?? false,
                            'hours_used' => $reservedAt->diffInMinutes($reservedUntil) / 60,
                        ]);

                        Notification::make()
                            ->title('Reservation Created')
                            ->body('The reservation has been created successfully.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UpcomingClosuresWidget::class,
            SpaceStatsWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'upcoming' => Tab::make('Upcoming')
                ->icon('tabler-calendar-clock')
                ->badge(function () {
                    return Reservation::where('reserved_until', '>', now())
                        ->where('status', '!=', 'cancelled')
                        ->count();
                })
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->where('reserved_until', '>', now())
                    ->where('status', '!=', 'cancelled')
                    ->orderBy('reserved_at', 'asc')),

            'all' => Tab::make('All')
                ->icon('tabler-calendar')
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->orderBy('reserved_at', 'desc')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'upcoming';
    }

}
