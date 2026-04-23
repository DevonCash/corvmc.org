<?php

namespace App\Filament\Member\Resources\Reservations\Pages;

use App\Filament\Member\Resources\Reservations\ReservationResource;
use App\Models\User;
use Carbon\Carbon;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\States\ReservationState\Confirmed;
use CorvMC\SpaceManagement\States\ReservationState\Scheduled;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateReservation extends CreateRecord
{
    protected static string $resource = ReservationResource::class;

    /**
     * The payment method chosen by the user via submit button.
     */
    public ?string $paymentMethod = null;

    public function payWithStripe(): void
    {
        $this->paymentMethod = 'stripe';
        $this->create();
    }

    public function payWithCash(): void
    {
        $this->paymentMethod = 'cash';
        $this->create();
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Schedule Reservation')
            ->hidden(fn () => $this->shouldShowPaymentButtons());
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Extra form actions that appear alongside the default submit button.
     * When in the confirmation window with a cost, show two payment buttons.
     */
    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),

            Action::make('pay_stripe')
                ->label('Pay Online')
                ->icon('tabler-credit-card')
                ->color('success')
                ->submit('payWithStripe')
                ->visible(fn () => $this->shouldShowPaymentButtons()),

            Action::make('pay_cash')
                ->label('Pay with Cash')
                ->icon('tabler-cash')
                ->color('warning')
                ->submit('payWithCash')
                ->visible(fn () => $this->shouldShowPaymentButtons()),

            $this->getCancelFormAction(),
        ];
    }

    protected function handleRecordCreation(array $data): Reservation
    {
        $user = auth()->user();
        $startTime = Carbon::parse($data['reserved_at']);
        $endTime = Carbon::parse($data['reserved_until']);

        return RehearsalReservation::create([
            'reservable_type' => User::class,
            'reservable_id' => $user->id,
            'reserved_at' => $startTime,
            'reserved_until' => $endTime,
            'notes' => $data['notes'] ?? null,
            'status' => Scheduled::class,
            'is_recurring' => $data['is_recurring'] ?? false,
            'recurrence_pattern' => $data['recurrence_pattern'] ?? null,
            'hours_used' => $startTime->diffInMinutes($endTime) / 60,
        ]);
    }

    protected function afterCreate(): void
    {
        /** @var RehearsalReservation $reservation */
        $reservation = $this->record;
        $user = auth()->user();

        if (! $this->isWithinConfirmationWindow()) {
            return; // Path 3: stays Scheduled, no Order
        }

        try {
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
                // Path 2: fully discounted
                Finance::commit($order->fresh(), []);
                $reservation->status->transitionTo(Confirmed::class);

                return;
            }

            // Path 1: has a cost
            $method = $this->paymentMethod ?? 'stripe';
            $committed = Finance::commit($order->fresh(), [$method => $totalCents]);

            $reservation->status->transitionTo(Confirmed::class);

            if ($method === 'stripe') {
                $checkoutUrl = $committed->checkoutUrl();
                if ($checkoutUrl) {
                    session()->put('stripe_checkout_url', $checkoutUrl);
                }
            }
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

    protected function getRedirectUrl(): string
    {
        if ($checkoutUrl = session()->pull('stripe_checkout_url')) {
            return $checkoutUrl;
        }

        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    private function shouldShowPaymentButtons(): bool
    {
        $costCents = (int) ($this->data['cost'] ?? 0);

        return $costCents > 0 && $this->isFormWithinConfirmationWindow();
    }

    private function isWithinConfirmationWindow(): bool
    {
        return $this->record->reserved_at->lte(now()->addWeek());
    }

    private function isFormWithinConfirmationWindow(): bool
    {
        $date = $this->data['reservation_date'] ?? null;
        if (! $date) {
            return false;
        }

        return Carbon::parse($date, config('app.timezone'))->lte(now()->addWeek());
    }
}
