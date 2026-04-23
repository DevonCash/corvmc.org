<?php

namespace App\Filament\Staff\Resources\Orders\Actions;

use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\OrderState\Pending;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class CancelOrderAction
{
    public static function make(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon('tabler-x')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('This will cancel the order and reverse any credit deductions.')
            ->visible(fn (Order $record) => $record->status instanceof Pending)
            ->action(function (Order $record) {
                Finance::cancel($record);
                Notification::make()->title('Order cancelled')->success()->send();
            });
    }
}
