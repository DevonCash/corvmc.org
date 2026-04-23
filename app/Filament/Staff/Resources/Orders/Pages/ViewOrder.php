<?php

namespace App\Filament\Staff\Resources\Orders\Pages;

use App\Filament\Staff\Resources\Orders\Actions;
use App\Filament\Staff\Resources\Orders\OrderResource;
use App\Filament\Staff\Resources\Orders\RelationManagers\LineItemsRelationManager;
use App\Filament\Staff\Resources\Orders\RelationManagers\TransactionsRelationManager;
use App\Filament\Staff\Resources\Users\UserResource;
use CorvMC\Finance\Facades\Finance;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\OrderState\Pending;
use CorvMC\Finance\States\TransactionState\Pending as TransactionPending;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function resolveRecord(int|string $key): Order
    {
        return Order::with(['user', 'lineItems', 'transactions'])->findOrFail($key);
    }

    public function getTitle(): string
    {
        return "Order #{$this->record->id}";
    }

    public function getSubheading(): ?string
    {
        /** @var Order $record */
        $record = $this->record;
        $parts = [
            $record->status->getLabel(),
            $record->formattedTotal(),
        ];

        if ($record->user) {
            $parts[] = $record->user->name;
        }

        return implode('  ·  ', $parts);
    }

    public function infolist(Schema $schema): Schema
    {
        /** @var Order $record */
        $record = $this->record;

        return $schema
            ->columns(1)
            ->components([
                // Details
                Section::make()
                    ->compact()
                    ->schema([
                        Grid::make(['default' => 2, 'lg' => 4])->schema([
                            TextEntry::make('user.name')
                                ->label('User')
                                ->url(fn () => $record->user
                                    ? UserResource::getUrl('edit', ['record' => $record->user])
                                    : null
                                )
                                ->color('primary')
                                ->placeholder('—'),
                            TextEntry::make('created_at')
                                ->label('Created')
                                ->dateTime(),
                            TextEntry::make('settled_at')
                                ->label('Settled')
                                ->dateTime()
                                ->placeholder('—'),
                            TextEntry::make('notes')
                                ->label('Notes')
                                ->placeholder('—'),
                        ]),
                    ]),

                // Line items table
                Livewire::make(LineItemsRelationManager::class, [
                    'ownerRecord' => $record,
                    'pageClass' => static::class,
                ]),

                // Transactions table
                Livewire::make(TransactionsRelationManager::class, [
                    'ownerRecord' => $record,
                    'pageClass' => static::class,
                ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        /** @var Order $record */
        $record = $this->record;

        return [
            Action::make('markPaid')
                ->label('Mark as Paid')
                ->icon('tabler-coin')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('This will settle all pending cash transactions and mark the order as completed.')
                ->visible(fn () => $record->status instanceof Pending
                    && $record->transactions()->where('currency', 'cash')->whereState('status', TransactionPending::class)->exists()
                )
                ->action(function () use ($record) {
                    $record->transactions()
                        ->where('currency', 'cash')
                        ->whereState('status', TransactionPending::class)
                        ->each(fn ($txn) => Finance::settle($txn));

                    Notification::make()->title('Order marked as paid')->success()->send();
                    $this->refreshPage();
                }),

            Actions\CollectCashAction::make()
                ->after(fn () => $this->refreshPage()),

            Action::make('comp')
                ->label('Comp')
                ->icon('tabler-gift')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('This will comp the order, waiving all outstanding charges.')
                ->visible(fn () => $record->status instanceof Pending)
                ->action(function () use ($record) {
                    Finance::comp($record);
                    Notification::make()->title('Order comped')->success()->send();
                    $this->refreshPage();
                }),

            Action::make('cancel')
                ->label('Cancel')
                ->icon('tabler-x')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('This will cancel the order and reverse any credit deductions.')
                ->visible(fn () => $record->status instanceof Pending)
                ->action(function () use ($record) {
                    Finance::cancel($record);
                    Notification::make()->title('Order cancelled')->success()->send();
                    $this->refreshPage();
                }),

            Actions\RefundOrderAction::make()
                ->action(function () use ($record) {
                    Finance::refund($record);
                    Notification::make()->title('Order refunded')->success()->send();
                    $this->refreshPage();
                }),
        ];
    }

    private function refreshPage(): void
    {
        $this->record = $this->record->fresh(['user', 'lineItems', 'transactions']);
        $this->fillInfolist();
    }
}
