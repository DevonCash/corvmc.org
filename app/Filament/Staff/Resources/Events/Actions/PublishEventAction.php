<?php

namespace App\Filament\Staff\Resources\Events\Actions;

use CorvMC\Events\Models\Event;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;

class PublishEventAction
{
    public static function make(): Action
    {
        return Action::make('publish')
            ->tooltip(fn(?Event $record) => $record->published_at?->diffForHumans() ?? 'Not Published')
            ->label(function (?Event $record) {
                if (! $record) {
                    return 'Publish';
                }
                if ($record->isPublished()) {
                    return 'Published';
                }
                if ($record->published_at?->isFuture()) {
                    return 'Publishing';
                }
                return 'Publish';
            })
            ->icon(function (?Event $record) {
                if ($record?->isPublished()) {
                    return 'tabler-circle-check';
                }
                if ($record?->published_at?->isFuture()) {
                    return 'tabler-clock';
                }
                return 'tabler-send';
            })
            ->color(function (?Event $record) {
                if ($record?->isPublished()) {
                    return 'info';
                }
                if ($record?->published_at?->isFuture()) {
                    return 'warning';
                }
                return 'gray';
            })
            ->authorize('publish')
            ->modalHeading(function (?Event $record) {
                if ($record?->isPublished()) {
                    return 'Unpublish';
                }
                if ($record?->published_at?->isFuture()) {
                    return 'Edit Publication Schedule';
                }
                return 'Publish';
            })
            ->modalDescription(function (?Event $record) {
                if ($record?->isPublished()) {
                    return 'This will remove the event from public view.';
                }
                return 'Set when this event should become visible to the public.';
            })
            ->modalSubmitActionLabel(function (?Event $record) {
                if ($record?->isPublished()) {
                    return 'Unpublish';
                }
                return 'Save';
            })
            ->schema(fn(?Event $record) => $record?->isPublished() ? [
                TextEntry::make('published_at')
                    ->label('Published At')
                    ->state(fn() => $record->published_at?->format('M d, Y h:i A') ?? 'N/A'),
            ] : [
                DateTimePicker::make('published_at')
                    ->label('Publish At')
                    ->helperText('Leave as now to publish immediately')
                    ->required()
                    ->native(true)
                    ->seconds(false)
                    ->default(now()),
            ])
            ->fillForm(fn(?Event $record) => [
                'published_at' => $record?->published_at ?? now(),
            ])
            ->action(function (Event $record, array $data) {
                if ($record->isPublished()) {
                    $record->update(['published_at' => null]);
                    Notification::make()
                        ->title('Event Unpublished')
                        ->success()
                        ->send();
                } else {
                    $record->update(['published_at' => $data['published_at']]);
                    $isNow = $data['published_at'] <= now();
                    Notification::make()
                        ->title($isNow ? 'Event Published' : 'Publication Scheduled')
                        ->success()
                        ->send();
                }
            });
    }
}
