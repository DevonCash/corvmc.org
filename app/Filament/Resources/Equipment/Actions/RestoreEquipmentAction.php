<?php

namespace App\Filament\Resources\Equipment\Actions;

use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;

class RestoreEquipmentAction
{
    public static function make(): RestoreAction
    {
        return RestoreAction::make()
            ->label('Restore Equipment')
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->modalHeading(fn ($record) => "Restore {$record->name}")
            ->modalDescription(function ($record) {
                $info = [];
                
                if ($record->is_kit && $record->children()->count() > 0) {
                    $count = $record->children()->count();
                    $info[] = "ℹ️ This kit has {$count} component(s) that will also be restored";
                }
                
                $info[] = "📅 Equipment was deleted on: " . $record->deleted_at->format('M j, Y g:i A');
                
                $base = "Restore this equipment to the active library?";
                
                return $base . "\n\n" . implode("\n", $info);
            })
            ->requiresConfirmation()
            ->successNotification(
                Notification::make()
                    ->success()
                    ->title('Equipment Restored')
                    ->body('Equipment has been restored to the active library.')
            )
            ->visible(fn ($record) => 
                auth()->user()->can('restore equipment') && 
                $record->trashed()
            );
    }
}