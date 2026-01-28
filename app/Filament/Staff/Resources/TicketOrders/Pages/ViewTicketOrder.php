<?php

namespace App\Filament\Staff\Resources\TicketOrders\Pages;

use App\Filament\Staff\Resources\TicketOrders\TicketOrderResource;
use CorvMC\Events\Actions\Tickets\RefundTicketOrder;
use CorvMC\Events\Models\TicketOrder;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewTicketOrder extends ViewRecord
{
    protected static string $resource = TicketOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refund')
                ->label('Refund Order')
                ->icon('tabler-receipt-refund')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Refund Order')
                ->modalDescription(fn () =>
                    "Are you sure you want to refund {$this->record->quantity} ticket(s) for \${$this->record->total->getAmount()->toFloat()}? This action cannot be undone."
                )
                ->visible(fn () => $this->record->canRefund())
                ->action(function () {
                    RefundTicketOrder::run($this->record, 'Refunded by staff');

                    $this->refreshFormData(['status', 'refunded_at']);
                }),
        ];
    }
}
