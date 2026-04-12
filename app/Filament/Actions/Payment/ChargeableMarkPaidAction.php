<?php

namespace App\Filament\Actions\Payment;

use App\Filament\Shared\Actions\Action;
use CorvMC\Finance\Contracts\Chargeable;
use CorvMC\Finance\Data\PaymentData;
use CorvMC\Finance\Services\PaymentService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament Action for marking any chargeable entity as paid.
 * 
 * This action handles the UI concerns for recording payments
 * and delegates business logic to the PaymentService.
 * Works with any model that implements the Chargeable interface.
 */
class ChargeableMarkPaidAction
{
    public static function make(): Action
    {
        return Action::make('mark_paid')
            ->label('Mark Paid')
            ->icon('tabler-cash')
            ->color('success')
            ->authorize('manage')
            ->visible(fn (Model $record) => 
                $record instanceof Chargeable && $record->needsPayment()
            )
            ->schema([
                Select::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'cash' => 'Cash',
                        'card' => 'Credit/Debit Card',
                        'venmo' => 'Venmo',
                        'paypal' => 'PayPal',
                        'zelle' => 'Zelle',
                        'check' => 'Check',
                        'other' => 'Other',
                    ])
                    ->required(),
                Textarea::make('payment_notes')
                    ->label('Payment Notes')
                    ->placeholder('Optional notes about the payment...')
                    ->rows(2),
            ])
            ->action(function (Model $record, array $data) {
                if (! $record instanceof Chargeable) {
                    return;
                }

                // Create DTO from form data
                $paymentData = PaymentData::from([
                    'chargeable' => $record,
                    'paymentMethod' => $data['payment_method'],
                    'notes' => $data['payment_notes'] ?? null,
                ]);

                // Use service to record payment
                $service = app(PaymentService::class);
                $service->recordPayment($paymentData);

                Notification::make()
                    ->title('Payment recorded')
                    ->success()
                    ->send();
            });
    }

    /**
     * Bulk action for marking multiple chargeable entities as paid.
     */
    public static function bulkAction(): Action
    {
        return Action::make('mark_paid_bulk')
            ->label('Mark as Paid')
            ->icon('tabler-cash')
            ->color('success')
            ->authorize('manage')
            ->schema([
                Select::make('payment_method')
                    ->label('Payment Method')
                    ->options([
                        'cash' => 'Cash',
                        'card' => 'Credit/Debit Card',
                        'venmo' => 'Venmo',
                        'paypal' => 'PayPal',
                        'zelle' => 'Zelle',
                        'check' => 'Check',
                        'other' => 'Other',
                    ])
                    ->required(),
                Textarea::make('payment_notes')
                    ->label('Payment Notes')
                    ->placeholder('Optional notes about the payment...')
                    ->rows(2),
            ])
            ->action(function (Collection $records, array $data) {
                $service = app(PaymentService::class);
                $count = 0;

                foreach ($records as $record) {
                    if ($record instanceof Chargeable && $record->needsPayment()) {
                        $paymentData = PaymentData::from([
                            'chargeable' => $record,
                            'paymentMethod' => $data['payment_method'],
                            'notes' => $data['payment_notes'] ?? null,
                        ]);

                        $service->recordPayment($paymentData);
                        $count++;
                    }
                }

                Notification::make()
                    ->title("{$count} payments recorded")
                    ->success()
                    ->send();
            });
    }
}