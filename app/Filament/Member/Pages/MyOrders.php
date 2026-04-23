<?php

namespace App\Filament\Member\Pages;

use App\Models\User;
use CorvMC\Finance\Models\Order;
use CorvMC\Finance\States\OrderState\Pending;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyOrders extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-receipt';

    protected string $view = 'filament.pages.my-orders';

    protected static string|\UnitEnum|null $navigationGroup = 'My Account';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Order History';

    protected static ?string $slug = 'orders';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->where('user_id', auth()->id())
                    ->with('lineItems', 'transactions')
            )
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->width(0)
                    ->sortable(),

                IconColumn::make('status')
                    ->label('')
                    ->width(0)
                    ->tooltip(fn (Order $record) => $record->status?->getLabel()),

                TextColumn::make('primary_product_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(function (Order $record): ?string {
                        $lineItem = $record->lineItems->first(fn ($li) => ! $li->isDiscount());

                        return $lineItem?->product_type;
                    })
                    ->formatStateUsing(fn (?string $state) => $state
                        ? ucwords(str_replace('_', ' ', $state))
                        : '—'
                    ),

                TextColumn::make('description')
                    ->label('Description')
                    ->getStateUsing(function (Order $record): string {
                        $lineItem = $record->lineItems->first(fn ($li) => ! $li->isDiscount());

                        return $lineItem?->description ?? '—';
                    }),

                TextColumn::make('total_amount')
                    ->label('Amount')
                    ->money('USD', divideBy: 100)
                    ->color(fn (Order $record) => $record->status instanceof Pending ? 'danger' : null)
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Date')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                \App\Filament\Staff\Resources\Orders\Actions\RetryPaymentAction::make(),
            ])
            ->paginated([10, 25]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = User::me();

        if (! $user) {
            return false;
        }

        return \Schema::hasTable('orders')
            && Order::where('user_id', $user->id)->exists();
    }
}
