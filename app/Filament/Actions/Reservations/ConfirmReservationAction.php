<?php

namespace App\Filament\Actions\Reservations;

use Carbon\Carbon;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\SpaceManagement\Models\RehearsalReservation;
use CorvMC\SpaceManagement\States\ReservationState\Confirmed;
use CorvMC\SpaceManagement\States\ReservationState\Scheduled;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class ConfirmReservationAction
{
    public static function make(): Action
    {
        return Action::make('confirm_reservation')
            ->label('Confirm')
            ->icon('tabler-calendar-check')
            ->color('success')
            ->visible(fn (RehearsalReservation $record) => static::canConfirm($record))
            ->fillForm(function (RehearsalReservation $record): array {
                $user = $record->getResponsibleUser();
                $lineItems = Finance::price([$record], $user);
                $totalCents = (int) $lineItems->sum('amount');
                $hours = $record->reserved_at->floatDiffInHours($record->reserved_until);
                $discountBlocks = (int) abs($lineItems->filter->isDiscount()->sum('quantity'));

                return [
                    'user_id' => $user->id,
                    'reserved_at' => $record->reserved_at,
                    'reserved_until' => $record->reserved_until,
                    'notes' => $record->notes,
                    'is_recurring' => $record->is_recurring,
                    'cost' => max(0, $totalCents),
                    'free_hours_used' => $discountBlocks,
                    'hours_used' => $hours,
                ];
            })
            ->form([
                Hidden::make('user_id'),
                Hidden::make('reserved_at'),
                Hidden::make('reserved_until'),
                Hidden::make('notes'),
                Hidden::make('is_recurring'),
                Hidden::make('cost')->default(0),
                Hidden::make('free_hours_used')->default(0),
                Hidden::make('hours_used')->default(0),

                ViewField::make('reservation_summary')
                    ->view('space-management::filament.components.reservation-summary')
                    ->columnSpanFull(),
            ])
            ->modalHeading('Confirm Reservation')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->extraModalFooterActions(function (Action $action) {
                $record = $action->getRecord();
                $user = $record->getResponsibleUser();
                $lineItems = Finance::price([$record], $user);
                $totalCents = (int) $lineItems->sum('amount');

                if ($totalCents <= 0) {
                    return [
                        $action->makeModalSubmitAction('confirmFree')
                            ->label('Reserve')
                            ->icon('tabler-calendar-check')
                            ->color('success')
                            ->action(function (RehearsalReservation $record) {
                                static::handleConfirm($record, 'free');
                            }),
                    ];
                }

                return [
                    $action->makeModalSubmitAction('confirmStripe')
                        ->label('Pay Online')
                        ->icon('tabler-credit-card')
                        ->color('success')
                        ->action(function (RehearsalReservation $record) {
                            static::handleConfirm($record, 'stripe');
                        }),
                    $action->makeModalSubmitAction('confirmCash')
                        ->label('Pay with Cash')
                        ->icon('tabler-cash')
                        ->color('warning')
                        ->action(function (RehearsalReservation $record) {
                            static::handleConfirm($record, 'cash');
                        }),
                ];
            })
            ->action(fn () => null);
    }

    private static function handleConfirm(RehearsalReservation $record, string $paymentMethod): void
    {
        $user = $record->getResponsibleUser();
        $lineItems = Finance::price([$record], $user);
        $totalCents = (int) $lineItems->sum('amount');

        // Free reservation — just confirm, no payment needed
        if ($totalCents <= 0) {
            $record->status->transitionTo(Confirmed::class);

            Notification::make()
                ->title('Reservation confirmed')
                ->body('No payment required.')
                ->success()
                ->send();

            return;
        }

        $order = Order::create([
            'user_id' => $user->id,
            'total_amount' => 0,
        ]);

        foreach ($lineItems as $lineItem) {
            $lineItem->order_id = $order->id;
            $lineItem->save();
        }

        $order->update(['total_amount' => $totalCents]);

        if ($paymentMethod === 'stripe') {
            $committed = Finance::commit($order->fresh(), ['stripe' => $totalCents]);
            $record->status->transitionTo(Confirmed::class);

            $checkoutUrl = $committed->checkoutUrl();
            if ($checkoutUrl) {
                redirect($checkoutUrl);

                return;
            }

            Notification::make()
                ->title('Reservation confirmed')
                ->body('Payment session created.')
                ->success()
                ->send();
        } else {
            Finance::commit($order->fresh(), ['cash' => $totalCents]);
            $record->status->transitionTo(Confirmed::class);

            Notification::make()
                ->title('Reservation confirmed')
                ->body('Please bring ' . $order->formattedTotal() . ' in cash to the space.')
                ->success()
                ->send();
        }
    }

    private static function canConfirm(RehearsalReservation $record): bool
    {
        if (! $record->status instanceof Scheduled) {
            return false;
        }

        // Must be within the confirmation window
        return $record->reserved_at->lte(now()->addWeek());
    }
}
