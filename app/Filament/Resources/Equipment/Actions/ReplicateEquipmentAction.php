<?php

namespace App\Filament\Resources\Equipment\Actions;

use App\Models\Equipment;
use Filament\Actions\ReplicateAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ReplicateEquipmentAction
{
    public static function make(): ReplicateAction
    {
        return ReplicateAction::make()
            ->label('Duplicate Equipment')
            ->icon('tabler-copy')
            ->color('secondary')
            ->modalHeading(fn ($record) => "Duplicate {$record->name}")
            ->modalDescription('Create a copy of this equipment with similar specifications')
            ->excludeAttributes([
                'serial_number',
                'status',
                'created_at',
                'updated_at',
                'deleted_at',
            ])
            ->beforeReplicaSaved(function (Equipment $replica, Equipment $original): void {
                // Modify the name to indicate it's a duplicate
                $replica->name = $original->name . ' (Copy)';

                // Clear serial number since it should be unique
                $replica->serial_number = null;

                // Set status to available for new equipment
                $replica->status = 'available';

                // Don't copy kit relationships - make it standalone
                $replica->parent_equipment_id = null;
                $replica->is_kit = false;
                $replica->can_lend_separately = true;
                $replica->sort_order = 0;
            })
            ->successNotification(
                Notification::make()
                    ->success()
                    ->title('Equipment Duplicated')
                    ->body(fn ($replica) => "Created duplicate: {$replica->name}")
            )
            ->successRedirectUrl(fn ($replica) =>
                route('filament.member.resources.equipment.edit', $replica)
            )
            ->visible(fn ($record) =>
                Auth::user()->can('create equipment')
            );
    }
}
