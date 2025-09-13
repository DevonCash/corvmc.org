<?php

namespace App\Filament\Resources\Reservations\Tables;

use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Member')
                    ->toggleable()
                    ->toggleable(isToggledHiddenByDefault: !User::me()->can('manage practice space'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('time_range')
                    ->label('Time Range')
                    ->state(function (Reservation $record): string {
                        return $record->time_range;
                    })
                    ->icon(fn($record) => $record->is_recurring ? 'heroicon-o-arrow-path' : null)
                    ->iconPosition(IconPosition::After)
                    ->searchable()
                    ->sortable(['reserved_at']),

                TextColumn::make('duration')
                    ->label('Duration')
                    ->getStateUsing(function (Reservation $record): string {
                        return number_format($record->duration, 1) . ' hrs';
                    })
                    ->sortable(['reserved_at', 'reserved_until']),

                TextColumn::make('status_display')
                    ->label('Status')
                    ->badge()
                    ->color(fn(Reservation $record): string => match ($record->status) {
                        'pending' => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(['status'])
                    ->sortable(['status']),

                TextColumn::make('cost_display')
                    ->label('Cost')
                    ->getStateUsing(function (Reservation $record): string {
                        return $record->cost_display;
                    })
                    ->sortable(['cost']),

                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->formatStateUsing(function (string $state, Reservation $record): string {
                        return $record->payment_status_badge['label'];
                    })
                    ->color(function (string $state, Reservation $record): string {
                        return $record->payment_status_badge['color'];
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('reserved_at', 'asc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->multiple(),

                SelectFilter::make('payment_status')
                    ->label('Payment Status')
                    ->options([
                        'unpaid' => 'Unpaid',
                        'paid' => 'Paid',
                        'comped' => 'Comped',
                        'refunded' => 'Refunded',
                    ])
                    ->multiple(),

                SelectFilter::make('user')
                    ->visible(User::me()->can('manage practice space'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From Date'),
                        DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('reserved_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('reserved_at', '<=', $date),
                            );
                    }),

                Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn(Builder $query): Builder => $query->whereMonth('reserved_at', now()->month)),

                Filter::make('recurring')
                    ->label('Recurring Only')
                    ->query(fn(Builder $query): Builder => $query->where('is_recurring', true)),

                Filter::make('free_hours_used')
                    ->label('Used Free Hours')
                    ->query(fn(Builder $query): Builder => $query->where('free_hours_used', '>', 0)),
            ])

            ->recordActions([
                Action::make('pay_stripe')
                    ->label('Pay Online')
                    ->icon('tabler-credit-card')
                    ->color('success')
                    ->visible(fn(Reservation $record) =>
                        $record->cost > 0 &&
                        $record->isUnpaid() &&
                        ($record->user_id === User::me()->id || User::me()->can('manage reservations'))
                    )
                    ->url(fn(Reservation $record) => route('reservations.payment.checkout', $record))
                    ->openUrlInNewTab(false),

                ActionGroup::make([
                    Action::make('mark_paid')
                        ->label('Mark Paid')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(fn(Reservation $record) =>
                        User::me()->can('manage reservations') &&
                            $record->cost > 0 && $record->isUnpaid())
                        ->schema([
                            Select::make('payment_method')
                                ->label('Payment Method')
                                ->options([
                                    'cash' => 'Cash',
                                    'card' => 'Credit/Debit Card',
                                    'venmo' => 'Venmo',
                                    'paypal' => 'PayPal',
                                    'zelle' => 'Zelle',
                                    'check' => 'Check',
                                    'other' => 'Other',
                                ])
                                ->required(),
                            Textarea::make('payment_notes')
                                ->label('Payment Notes')
                                ->placeholder('Optional notes about the payment...')
                                ->rows(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            $record->markAsPaid($data['payment_method'], $data['payment_notes']);

                            Notification::make()
                                ->title('Payment recorded')
                                ->body("Reservation marked as paid via {$data['payment_method']}")
                                ->success()
                                ->send();
                        }),

                    Action::make('mark_comped')
                        ->label('Comp')
                        ->icon('heroicon-o-gift')
                        ->color('info')
                        ->visible(fn(Reservation $record) =>
                        User::me()->can('manage reservations') &&
                            $record->cost > 0 && $record->isUnpaid())
                        ->schema([
                            Textarea::make('comp_reason')
                                ->label('Comp Reason')
                                ->placeholder('Why is this reservation being comped?')
                                ->required()
                                ->rows(2),
                        ])
                        ->action(function (Reservation $record, array $data) {
                            $record->markAsComped($data['comp_reason']);

                            Notification::make()
                                ->title('Reservation comped')
                                ->body('Reservation has been marked as comped')
                                ->success()
                                ->send();
                        }),

                    Action::make('cancel')
                        ->icon('tabler-calendar-x')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Reservation $record) {
                            \App\Facades\ReservationService::cancelReservation($record);

                            Notification::make()
                                ->title('Reservation Cancelled')
                                ->body('The reservation has been cancelled.')
                                ->success()
                                ->send();
                        }),

                    ViewAction::make(),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    Action::make('mark_paid_bulk')
                        ->label('Mark as Paid')
                        ->icon('heroicon-o-banknotes')
                        ->color('success')
                        ->visible(fn() => User::me()->can('manage reservations'))
                        ->schema([
                            Select::make('payment_method')
                                ->label('Payment Method')
                                ->options([
                                    'cash' => 'Cash',
                                    'card' => 'Credit/Debit Card',
                                    'venmo' => 'Venmo',
                                    'paypal' => 'PayPal',
                                    'zelle' => 'Zelle',
                                    'check' => 'Check',
                                    'other' => 'Other',
                                ])
                                ->required(),
                            Textarea::make('payment_notes')
                                ->label('Payment Notes')
                                ->placeholder('Optional notes about the payment...')
                                ->rows(2),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->cost > 0 && $record->isUnpaid()) {
                                    $record->markAsPaid($data['payment_method'], $data['payment_notes']);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title('Payments recorded')
                                ->body("{$count} reservations marked as paid")
                                ->success()
                                ->send();
                        }),

                    Action::make('mark_comped_bulk')
                        ->label('Comp Reservations')
                        ->icon('heroicon-o-gift')
                        ->color('info')
                        ->visible(fn() => User::me()->can('manage reservations'))
                        ->schema([
                            Textarea::make('comp_reason')
                                ->label('Comp Reason')
                                ->placeholder('Why are these reservations being comped?')
                                ->required()
                                ->rows(2),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->cost > 0 && $record->isUnpaid()) {
                                    $record->markAsComped($data['comp_reason']);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title('Reservations comped')
                                ->body("{$count} reservations marked as comped")
                                ->success()
                                ->send();
                        }),

                    Action::make('bulk_cancel')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                \App\Facades\ReservationService::cancelReservation($record);
                            }

                            Notification::make()
                                ->title('Reservations cancelled')
                                ->body("{$records->count()} reservations marked as cancelled")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No reservations found')
            ->emptyStateDescription('Start by creating your first practice space reservation.');
    }
}
