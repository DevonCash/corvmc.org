<?php

namespace App\Filament\Resources\Reservations\Actions;

use App\Models\Reservation;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class MarkCompedAction
{
    public static function make(): Action
    {
        return Action::make('mark_comped')
            ->label('Comp')
            ->icon('tabler-gift')
            ->color('info')
            ->visible(fn(Reservation $record) =>
                Auth::user()->can('manage reservations') &&
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
            });
    }
}
