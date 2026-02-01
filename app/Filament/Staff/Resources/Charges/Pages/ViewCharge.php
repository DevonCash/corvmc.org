<?php

namespace App\Filament\Staff\Resources\Charges\Pages;

use App\Filament\Staff\Resources\Charges\ChargeResource;
use CorvMC\Finance\Models\Charge;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCharge extends ViewRecord
{
    protected static string $resource = ChargeResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Charge $record */
        $record = $this->record;

        return [
            Action::make('markPaid')
                ->label('Mark as Paid')
                ->icon('tabler-coin')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $record->status->isPending())
                ->action(function () use ($record) {
                    $record->markAsPaid('manual', null, 'Marked paid by staff');
                    Notification::make()->title('Charge marked as paid')->success()->send();
                    $this->refreshFormData(['status', 'paid_at', 'payment_method']);
                }),

            Action::make('markComped')
                ->label('Comp')
                ->icon('tabler-gift')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn () => $record->status->isPending())
                ->action(function () use ($record) {
                    $record->markAsComped('Comped by staff');
                    Notification::make()->title('Charge comped')->success()->send();
                    $this->refreshFormData(['status', 'paid_at']);
                }),

            Action::make('markRefunded')
                ->label('Refund')
                ->icon('tabler-receipt-refund')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('This will mark the charge as refunded. If this was a Stripe payment, you should also process the refund in the Stripe dashboard.')
                ->visible(fn () => $record->status->isPaid())
                ->action(function () use ($record) {
                    $record->markAsRefunded('Refunded by staff');
                    Notification::make()->title('Charge refunded')->success()->send();
                    $this->refreshFormData(['status']);
                }),
        ];
    }
}
