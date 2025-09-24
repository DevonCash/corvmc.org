<?php

namespace App\Filament\Resources\Equipment\Actions;

use Filament\Actions\ForceDeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ForceDeleteEquipmentAction
{
    public static function make(): ForceDeleteAction
    {
        return ForceDeleteAction::make()
            ->label('Permanently Delete')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->modalHeading(fn ($record) => "Permanently Delete {$record->name}")
            ->modalDescription(function ($record) {
                $warnings = [];
                
                if ($record->is_kit && $record->children()->count() > 0) {
                    $count = $record->children()->count();
                    $warnings[] = "⚠️ This kit has {$count} component(s) that will also be permanently deleted";
                }
                
                if ($record->loans()->count() > 0) {
                    $count = $record->loans()->count();
                    $warnings[] = "⚠️ This equipment has {$count} loan record(s) that will be permanently deleted";
                }
                
                $warnings[] = "🚨 THIS ACTION CANNOT BE UNDONE";
                
                $base = "Permanently delete this equipment and all associated data?";
                
                return $base . "\n\n" . implode("\n", $warnings);
            })
            ->requiresConfirmation()
            ->modalSubmitActionLabel('Permanently Delete')
            ->before(function ($record) {
                // Log the permanent deletion for audit purposes
                activity()
                    ->performedOn($record)
                    ->causedBy(Auth::user())
                    ->withProperties([
                        'equipment_name' => $record->name,
                        'equipment_type' => $record->type,
                        'serial_number' => $record->serial_number,
                        'acquisition_type' => $record->acquisition_type,
                        'estimated_value' => $record->estimated_value,
                        'is_kit' => $record->is_kit,
                        'component_count' => $record->is_kit ? $record->children()->count() : 0,
                        'loan_history_count' => $record->loans()->count(),
                    ])
                    ->log('equipment_permanently_deleted');
                    
                return true;
            })
            ->successNotification(
                Notification::make()
                    ->success()
                    ->title('Equipment Permanently Deleted')
                    ->body('Equipment and all associated data has been permanently removed.')
            )
            ->visible(fn ($record) => 
                Auth::user()->can('force delete equipment') && 
                $record->trashed()
            );
    }
}