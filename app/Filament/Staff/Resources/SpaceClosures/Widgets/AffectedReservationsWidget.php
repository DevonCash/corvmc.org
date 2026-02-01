<?php

namespace App\Filament\Staff\Resources\SpaceClosures\Widgets;

use App\Filament\Member\Resources\Reservations\Tables\Columns\ReservationColumns;
use CorvMC\SpaceManagement\Actions\Reservations\CancelReservation;
use CorvMC\SpaceManagement\Enums\ReservationStatus;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Models\SpaceClosure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Reactive;

class AffectedReservationsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    #[Reactive]
    public ?Model $record = null;

    public function table(Table $table): Table
    {
        /** @var SpaceClosure|null $closure */
        $closure = $this->record;

        $query = $closure
            ? Reservation::query()
                ->with(['reservable', 'user', 'charge'])
                ->where('status', '!=', ReservationStatus::Cancelled)
                ->where('reserved_until', '>', $closure->starts_at)
                ->where('reserved_at', '<', $closure->ends_at)
            : Reservation::query()->whereRaw('1 = 0');

        return $table
            ->query($query)
            ->heading('Affected Reservations')
            ->description('Reservations that overlap with this closure period')
            ->columns([
                ReservationColumns::statusDisplay(),
                ReservationColumns::responsibleUser(),
                ReservationColumns::timeRange(),
                ReservationColumns::costDisplay(),
            ])
            ->defaultSort('reserved_at', 'asc')
            ->recordClasses(fn (Reservation $record) => 'bg-danger-50 dark:bg-danger-950/20')
            ->recordActions([
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('tabler-x')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to cancel this reservation? The member will be notified.')
                    ->visible(fn (Reservation $record) => $record->status->isActive())
                    ->action(function (Reservation $record) use ($closure) {
                        if ($closure) {
                            CancelReservation::run($record, "Space closure: {$closure->type->getLabel()}");

                            Notification::make()
                                ->title('Reservation cancelled')
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('cancelAll')
                        ->label('Cancel Selected')
                        ->icon('tabler-x')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Cancel Selected Reservations')
                        ->modalDescription('Are you sure you want to cancel all selected reservations? Members will be notified.')
                        ->action(function (Collection $records) use ($closure) {
                            if (! $closure) {
                                return;
                            }

                            $count = 0;

                            foreach ($records as $record) {
                                if ($record->status->isActive()) {
                                    CancelReservation::run($record, "Space closure: {$closure->type->getLabel()}");
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title("{$count} ".str('reservation')->plural($count).' cancelled')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('No affected reservations')
            ->emptyStateDescription('No reservations overlap with this closure period.')
            ->emptyStateIcon('tabler-calendar-check');
    }
}
