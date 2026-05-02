<?php

namespace App\Filament\Member\Resources\Reservations\Pages;

use App\Filament\Member\Resources\Reservations\ReservationResource;
use App\Filament\Member\Resources\Reservations\Schemas\ReservationForm;
use App\Filament\Member\Resources\Reservations\Widgets\RecurringSeriesTableWidget;
use App\Models\User;
use Carbon\Carbon;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\States\ReservationState\Confirmed;
use CorvMC\SpaceManagement\States\ReservationState\Scheduled;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class ListReservations extends ListRecords
{
    protected static string $resource = ReservationResource::class;

    public string $paymentMethod = 'stripe';

    public function submitWithPaymentMethod(string $method): void
    {
        $this->paymentMethod = $method;
        $this->callMountedAction();
    }

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

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['charge']))
            ->emptyStateActions([
                $this->getReserveSpaceAction(),
            ]);
    }

    public function getTabs(): array
    {
        return [
            'upcoming' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->where('reserved_until', '>', now())
                    ->whereNotState('status', \CorvMC\SpaceManagement\States\ReservationState\Cancelled::class)
                    ->orderBy('reserved_at', 'asc')),
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
            ->modifyWizardUsing(fn ($wizard) => $wizard
                ->submitAction(ReservationForm::getSubmitActionHtml('mountedActions.0.data'))
            )
            ->action(fn (array $data) => $this->createReservationWithPayment($data, $this->paymentMethod));
    }

    private function createReservationWithPayment(array $data, string $paymentMethod): void
    {
        $user = User::find($data['user_id']);

        $reservedAt = $data['reserved_at'] instanceof Carbon
            ? $data['reserved_at']
            : Carbon::parse($data['reserved_at']);
        $reservedUntil = $data['reserved_until'] instanceof Carbon
            ? $data['reserved_until']
            : Carbon::parse($data['reserved_until']);

        // Create reservation as Scheduled
        $reservation = RehearsalReservation::create([
            'reservable_type' => 'user',
            'reservable_id' => $user->id,
            'reserved_at' => $reservedAt,
            'reserved_until' => $reservedUntil,
            'status' => Scheduled::class,
            'notes' => $data['notes'] ?? null,
            'is_recurring' => $data['is_recurring'] ?? false,
            'hours_used' => $reservedAt->diffInMinutes($reservedUntil) / 60,
        ]);

        // Check if within confirmation window
        if ($reservedAt->gt(now()->addWeek())) {
            Notification::make()
                ->title('Reservation Scheduled')
                ->body('We\'ll send you a confirmation reminder before your reservation.')
                ->success()
                ->send();

            return;
        }

        try {
            // Guard against duplicate orders (double-click / concurrent request)
            $existingOrder = Finance::findActiveOrder($reservation);
            if ($existingOrder) {
                Notification::make()
                    ->title('Order already exists')
                    ->body('A payment is already in progress for this reservation.')
                    ->warning()
                    ->send();

                return;
            }

            // Price and create Order
            $lineItems = Finance::price([$reservation], $user);
            $totalCents = (int) $lineItems->sum('amount');

            $order = Order::create([
                'user_id' => $user->id,
                'total_amount' => 0,
            ]);

            foreach ($lineItems as $lineItem) {
                $lineItem->order_id = $order->id;
                $lineItem->save();
            }

            $order->update(['total_amount' => $totalCents]);

            if ($totalCents <= 0) {
                // Fully discounted
                Finance::commit($order->fresh(), []);
                $reservation->status->transitionTo(Confirmed::class);

                Notification::make()
                    ->title('Reservation Confirmed')
                    ->body('Your free hours cover this reservation — no payment needed.')
                    ->success()
                    ->send();

                return;
            }

            // Commit with chosen payment rail
            $committed = Finance::commit($order->fresh(), [$paymentMethod => $totalCents]);
            $reservation->status->transitionTo(Confirmed::class);

            if ($paymentMethod === 'stripe') {
                $checkoutUrl = $committed->checkoutUrl();
                if ($checkoutUrl) {
                    $this->redirect($checkoutUrl);

                    return;
                }
            }

            Notification::make()
                ->title('Reservation Confirmed')
                ->body($paymentMethod === 'cash'
                    ? 'Please bring ' . $order->formattedTotal() . ' in cash to the space.'
                    : 'Your reservation has been confirmed.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Log::error('Failed to create order for reservation', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Payment Error')
                ->body('Your reservation was created but we couldn\'t set up payment: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getReserveSpaceAction(),
        ];
    }


    public function getDefaultActiveTab(): string|int|null
    {
        return 'upcoming';
    }
}
