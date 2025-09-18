<?php

namespace App\Filament\Resources\Reservations\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class MarkPaidBulkAction
{
    public static function make(): Action
    {
        return Action::make('mark_paid_bulk')
            ->label('Mark as Paid')
            ->icon('tabler-cash')
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
            });
    }
}
