<?php

namespace App\Filament\Staff\Resources\Orders\Actions;

use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\OrderState\Pending;
use CorvMC\Finance\States\TransactionState\Pending as TransactionPending;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class MarkPaidAction
{
    public static function make(): Action
    {
        return Action::make('markPaid')
            ->label('Mark as Paid')
            ->icon('tabler-coin')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('This will settle all pending cash transactions and mark the order as completed.')
            ->visible(fn (Order $record) => $record->status instanceof Pending
                && $record->transactions()->where('currency', 'cash')->whereState('status', TransactionPending::class)->exists()
            )
            ->action(function (Order $record) {
                $record->transactions()
                    ->where('currency', 'cash')
                    ->whereState('status', TransactionPending::class)
                    ->each(fn ($txn) => Finance::settle($txn));

                Notification::make()->title('Order marked as paid')->success()->send();
            });
    }
}
