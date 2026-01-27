<?php

namespace App\Filament\Staff\Resources\Events\Actions;

use CorvMC\Events\Actions\CancelEvent;
use CorvMC\Events\Enums\EventStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class CancelEventAction
{
    public static function make(): Action
    {
        return Action::make('cancel')
            ->label('Cancel Event')
            ->icon('tabler-calendar-x')
            ->color('danger')
            ->visible(fn ($record) => $record->status !== EventStatus::Cancelled)
            ->authorize('cancel')
            ->schema([
                Textarea::make('reason')
                    ->label('Cancellation Reason')
                    ->placeholder('Why is this event being cancelled?')
                    ->helperText('This will be stored for reference but is not currently displayed publicly.'),
            ])
            ->modalHeading('Cancel Event')
            ->modalDescription('Are you sure you want to cancel this event? This action can be reversed by rescheduling.')
            ->modalSubmitActionLabel('Cancel Event')
            ->action(function ($record, array $data) {
                CancelEvent::run($record, $data['reason'] ?? null);

                Notification::make()
                    ->title('Event Cancelled')
                    ->body("'{$record->title}' has been cancelled.")
                    ->warning()
                    ->send();
            });
    }
}
