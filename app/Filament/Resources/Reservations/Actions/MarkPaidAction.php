<?php

namespace App\Filament\Resources\Reservations\Actions;

use App\Models\Reservation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class MarkPaidAction
{
    public static function make(): Action
    {
        return Action::make('mark_paid')
            ->label('Mark Paid')
            ->icon('tabler-cash')
            ->color('success')
            ->visible(fn(Reservation $record) =>
                Auth::user()->can('manage reservations') &&
                !$record->cost->isZero() && $record->isUnpaid())
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
            });
    }
}
