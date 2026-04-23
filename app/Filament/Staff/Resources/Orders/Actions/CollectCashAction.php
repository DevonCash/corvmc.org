<?php

namespace App\Filament\Staff\Resources\Orders\Actions;

use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\Models\Transaction;
use CorvMC\Finance\States\OrderState\Pending;
use CorvMC\Finance\States\TransactionState\Cancelled as TransactionCancelled;
use CorvMC\Finance\States\TransactionState\Pending as TransactionPending;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class CollectCashAction
{
    /**
     * Create an action that converts pending non-cash transactions to a
     * settled cash payment. Used when a Stripe payment fails and staff
     * collect cash at the door instead.
     */
    public static function make(): Action
    {
        return Action::make('collect_cash')
            ->label('Collect Cash')
            ->icon('tabler-cash')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('This will cancel any pending online payments and record a cash payment for the full amount.')
            ->visible(fn (Order $record) => $record->status instanceof Pending
                && $record->transactions()
                    ->where('type', 'payment')
                    ->whereState('status', TransactionPending::class)
                    ->where('currency', '!=', 'cash')
                    ->exists()
            )
            ->action(function (Order $record) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($record) {
                    // Void all pending non-cash transactions
                    $record->transactions()
                        ->where('type', 'payment')
                        ->whereState('status', TransactionPending::class)
                        ->where('currency', '!=', 'cash')
                        ->each(function (Transaction $txn) {
                            $txn->status->transitionTo(TransactionCancelled::class);
                            $txn->update(['cancelled_at' => now()]);
                        });

                    // Create and settle a cash transaction for the outstanding amount
                    $outstanding = $record->outstandingAmount();

                    if ($outstanding > 0) {
                        $cashTxn = Transaction::create([
                            'order_id' => $record->id,
                            'user_id' => $record->user_id,
                            'currency' => 'cash',
                            'amount' => $outstanding,
                            'type' => 'payment',
                            'metadata' => ['converted_from' => 'stripe'],
                        ]);

                        Finance::settle($cashTxn);
                    }
                });

                Notification::make()->title('Cash payment collected')->success()->send();
            });
    }
}
