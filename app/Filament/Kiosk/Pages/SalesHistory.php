<?php

namespace App\Filament\Kiosk\Pages;

use App\Actions\Sales\RefundSale;
use App\Enums\SaleStatus;
use App\Models\Sale;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\{Size, TextSize};
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class SalesHistory extends Page implements HasTable
{
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected string $view = 'filament.kiosk.pages.sales-history';

    protected static ?string $title = 'Sales History';

    protected static ?string $navigationLabel = 'Sales';

    protected static ?int $navigationSort = 11;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Sale::query()
                    ->with(['user', 'items'])
                    ->latest()
            )
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date/Time')
                    ->dateTime('M j, g:i A')
                    ->sortable()
                    ->size(TextSize::Medium),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Customer')
                    ->default('Walk-in')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_summary')
                    ->label('Items')
                    ->formatStateUsing(function (Sale $record) {
                        return $record->items->map(function ($item) {
                            return "{$item->description} ({$item->quantity})";
                        })->join(', ');
                    })
                    ->limit(50)
                    ->tooltip(function (Sale $record) {
                        return $record->items->map(function ($item) {
                            return "{$item->description} × {$item->quantity} = {$item->subtotal->formatTo('en_US')}";
                        })->join("\n");
                    }),

                Tables\Columns\TextColumn::make('total')
                    ->money('USD')
                    ->sortable()
                    ->size(TextSize::Large)
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('payment_method')
                    ->badge()
                    ->color(fn ($state) => match ($state->value) {
                        'cash' => 'success',
                        'card_on_file' => 'info',
                        'card_kiosk' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        SaleStatus::Completed => 'success',
                        SaleStatus::Refunded => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        SaleStatus::Completed->value => 'Completed',
                        SaleStatus::Refunded->value => 'Refunded',
                    ]),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'card_on_file' => 'Card on File',
                        'card_kiosk' => 'Card at Kiosk',
                    ]),
            ])
            ->recordActions([
                Action::make('refund')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->size(Size::Large)
                    ->requiresConfirmation()
                    ->modalHeading('Refund Sale')
                    ->modalDescription(fn (Sale $record) => "Refund {$record->total->formatTo('en_US')} to customer?")
                    ->visible(fn (Sale $record) => $record->status === SaleStatus::Completed)
                    ->action(function (Sale $record) {
                        try {
                            RefundSale::run($record);

                            Notification::make()
                                ->success()
                                ->title('Sale Refunded')
                                ->body("Refunded {$record->total->formatTo('en_US')}")
                                ->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Refund Failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->poll('30s');
    }

    public function getTodayTotal(): string
    {
        $total = Sale::whereDate('created_at', today())
            ->where('status', SaleStatus::Completed)
            ->sum('total');

        return '$'.number_format($total / 100, 2);
    }
}
