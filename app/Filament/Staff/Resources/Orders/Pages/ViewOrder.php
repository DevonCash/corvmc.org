<?php

namespace App\Filament\Staff\Resources\Orders\Pages;

use App\Filament\Staff\Resources\Orders\Actions;
use App\Filament\Staff\Resources\Orders\OrderResource;
use App\Filament\Staff\Resources\Orders\RelationManagers\LineItemsRelationManager;
use App\Filament\Staff\Resources\Orders\RelationManagers\TransactionsRelationManager;
use App\Filament\Staff\Resources\Users\UserResource;
use CorvMC\Finance\Models\Order;
use Filament\Infolists\Components\TextEntry;
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

        $refresh = fn () => $this->refreshPage();

        return [
            Actions\MarkPaidAction::make()->after($refresh),
            Actions\CollectCashAction::make()->after($refresh),
            Actions\CompOrderAction::make()->after($refresh),
            Actions\CancelOrderAction::make()->after($refresh),
            Actions\RefundOrderAction::make()->after($refresh),
        ];
    }

    private function refreshPage(): void
    {
        $this->record = $this->record->fresh(['user', 'lineItems', 'transactions']);
        $this->fillInfolist();
    }
}
