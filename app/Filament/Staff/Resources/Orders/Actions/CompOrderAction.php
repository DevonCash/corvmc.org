<?php

namespace App\Filament\Staff\Resources\Orders\Actions;

use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\OrderState\Pending;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class CompOrderAction
{
    public static function make(): Action
    {
        return Action::make('comp')
            ->label('Comp')
            ->icon('tabler-gift')
            ->color('info')
            ->requiresConfirmation()
            ->modalDescription('This will comp the order, waiving all outstanding charges.')
            ->visible(fn (Order $record) => $record->status instanceof Pending)
            ->action(function (Order $record) {
                Finance::comp($record);
                Notification::make()->title('Order comped')->success()->send();
            });
    }
}
