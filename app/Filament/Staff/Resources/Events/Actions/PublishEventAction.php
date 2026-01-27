<?php

namespace App\Filament\Staff\Resources\Events\Actions;

use CorvMC\Events\Actions\PublishEvent;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class PublishEventAction
{
    public static function make(): Action
    {
        return Action::make('publish')
            ->label('Publish')
            ->icon('tabler-send')
            ->color('success')
            ->visible(fn ($record) => ! $record->isPublished() && $record->canPublish())
            ->authorize('publish')
            ->requiresConfirmation()
            ->modalHeading('Publish Event')
            ->modalDescription('Are you sure you want to publish this event? It will become visible to the public.')
            ->modalSubmitActionLabel('Publish')
            ->action(function ($record) {
                PublishEvent::run($record);

                Notification::make()
                    ->title('Event Published')
                    ->body("'{$record->title}' is now live.")
                    ->success()
                    ->send();
            });
    }
}
