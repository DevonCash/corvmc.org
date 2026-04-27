<?php

namespace App\Filament\Staff\Resources\Volunteering\Shifts\Actions;

use CorvMC\Volunteering\Models\HourLog;
use CorvMC\Volunteering\Services\HourLogService;
use CorvMC\Volunteering\States\HourLogState\Pending;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class RejectHoursAction
{
    public static function make(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('tabler-x')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Reject Hours')
            ->modalDescription(fn (HourLog $record) => "Reject {$record->minutes} minutes submitted by {$record->user->name}?")
            ->visible(fn (HourLog $record) => $record->status instanceof Pending)
            ->authorize(fn (HourLog $record) => auth()->user()->can('reject', $record))
            ->schema([
                Textarea::make('notes')
                    ->label('Reason (optional)')
                    ->placeholder('Explain why these hours are being rejected...'),
            ])
            ->action(function (HourLog $record, array $data) {
                app(HourLogService::class)->reject(
                    $record,
                    auth()->user(),
                    $data['notes'] ?? null,
                );

                Notification::make()
                    ->title('Hours rejected')
                    ->success()
                    ->send();
            });
    }
}
