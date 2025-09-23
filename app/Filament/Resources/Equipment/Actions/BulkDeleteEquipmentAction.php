<?php

namespace App\Filament\Resources\Equipment\Actions;

use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;

class BulkDeleteEquipmentAction
{
    public static function make(): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->label('Delete Selected')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->modalHeading('Delete Selected Equipment')
            ->modalDescription(function ($records) {
                $count = $records->count();
                $checkedOut = $records->filter(fn ($record) => $record->is_checked_out)->count();
                $kits = $records->filter(fn ($record) => $record->is_kit)->count();
                
                $warnings = [];
                
                if ($checkedOut > 0) {
                    $warnings[] = "⚠️ {$checkedOut} item(s) are currently checked out and cannot be deleted";
                }
                
                if ($kits > 0) {
                    $warnings[] = "📦 {$kits} kit(s) selected - their components will also be deleted";
                }
                
                $base = "Delete {$count} selected equipment item(s)?";
                
                return $warnings ? $base . "\n\n" . implode("\n", $warnings) : $base;
            })
            ->deselectRecordsAfterCompletion()
            ->before(function ($records) {
                // Filter out equipment that can't be deleted
                $cannotDelete = $records->filter(fn ($record) => $record->is_checked_out);
                
                if ($cannotDelete->count() > 0) {
                    $names = $cannotDelete->pluck('name')->take(3)->join(', ');
                    $extra = $cannotDelete->count() > 3 ? ' and ' . ($cannotDelete->count() - 3) . ' more' : '';
                    
                    Notification::make()
                        ->warning()
                        ->title('Some Items Skipped')
                        ->body("Cannot delete checked-out equipment: {$names}{$extra}")
                        ->send();
                }
                
                return true;
            })
            ->action(function ($records) {
                // Only delete equipment that isn't checked out
                $deletableRecords = $records->filter(fn ($record) => !$record->is_checked_out);
                $deletedCount = 0;
                
                foreach ($deletableRecords as $record) {
                    try {
                        $record->delete();
                        $deletedCount++;
                    } catch (\Exception $e) {
                        // Log error but continue with other records
                        report($e);
                    }
                }
                
                if ($deletedCount > 0) {
                    Notification::make()
                        ->success()
                        ->title('Equipment Deleted')
                        ->body("Successfully deleted {$deletedCount} equipment item(s)")
                        ->send();
                }
            })
            ->requiresConfirmation()
            ->visible(fn () => Auth::user()->can('delete equipment'));
    }
}