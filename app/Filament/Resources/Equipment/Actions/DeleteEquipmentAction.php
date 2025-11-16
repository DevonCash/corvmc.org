<?php

namespace App\Filament\Resources\Equipment\Actions;

use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class DeleteEquipmentAction
{
    public static function make(): DeleteAction
    {
        return DeleteAction::make()
            ->label('Delete Equipment')
            ->icon('tabler-trash')
            ->color('danger')
            ->modalHeading(fn ($record) => "Delete {$record->name}")
            ->modalDescription(function ($record) {
                $warnings = [];

                if ($record->is_checked_out) {
                    $warnings[] = "⚠️ This equipment is currently checked out to {$record->currentLoan->borrower->name}";
                }

                if ($record->is_kit && $record->children()->count() > 0) {
                    $count = $record->children()->count();
                    $warnings[] = "⚠️ This kit has {$count} component(s) that will also be deleted";
                }

                if ($record->loans()->count() > 0) {
                    $count = $record->loans()->count();
                    $warnings[] = "📝 This equipment has {$count} loan record(s) in its history";
                }

                $base = 'Are you sure you want to delete this equipment? This action cannot be undone.';

                return $warnings ? $base."\n\n".implode("\n", $warnings) : $base;
            })
            ->requiresConfirmation()
            ->before(function ($record) {
                // Prevent deletion if equipment is checked out
                if ($record->is_checked_out) {
                    Notification::make()
                        ->danger()
                        ->title('Cannot Delete Equipment')
                        ->body('Equipment cannot be deleted while it is checked out to a member.')
                        ->send();

                    return false;
                }

                // Warn about kit components
                if ($record->is_kit && $record->children()->count() > 0) {
                    $componentCount = $record->children()->count();
                    $checkedOutComponents = $record->children()->where('status', 'checked_out')->count();

                    if ($checkedOutComponents > 0) {
                        Notification::make()
                            ->danger()
                            ->title('Cannot Delete Kit')
                            ->body("This kit has {$checkedOutComponents} component(s) that are currently checked out.")
                            ->send();

                        return false;
                    }
                }

                return true;
            })
            ->successNotification(
                Notification::make()
                    ->success()
                    ->title('Equipment Deleted')
                    ->body('Equipment has been permanently deleted from the library.')
            )
            ->visible(fn ($record) => Auth::user()->can('delete equipment') &&
                ! $record->is_checked_out
            );
    }
}
