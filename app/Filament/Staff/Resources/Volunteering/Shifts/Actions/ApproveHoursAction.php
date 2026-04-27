<?php

namespace App\Filament\Staff\Resources\Volunteering\Shifts\Actions;

use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Services\HourLogService;
use CorvMC\Volunteering\States\HourLogState\Pending;
use Filament\Actions\Action;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Notifications\Notification;

class ApproveHoursAction
{
    public static function make(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('tabler-check')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve Hours')
            ->modalDescription(fn (HourLog $record) => "Approve {$record->minutes} minutes submitted by {$record->user->name}?")
            ->visible(fn (HourLog $record) => $record->status instanceof Pending)
            ->authorize(fn (HourLog $record) => auth()->user()->can('approve', $record))
            ->schema([
                SpatieTagsInput::make('tags')
                    ->label('Tags (optional)')
                    ->placeholder('Add tags...'),
            ])
            ->action(function (HourLog $record, array $data) {
                app(HourLogService::class)->approve(
                    $record,
                    auth()->user(),
                    $data['tags'] ?? [],
                );

                Notification::make()
                    ->title('Hours approved')
                    ->success()
                    ->send();
            });
    }
}
