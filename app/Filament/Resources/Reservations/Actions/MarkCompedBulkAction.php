<?php

namespace App\Filament\Resources\Reservations\Actions;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class MarkCompedBulkAction
{
    public static function make(): Action
    {
        return Action::make('mark_comped_bulk')
            ->label('Comp Reservations')
            ->icon('tabler-gift')
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
            });
    }
}
