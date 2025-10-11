<?php

namespace App\Filament\Resources\Bands\Actions;

use App\Models\Band;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Auth;

class RemoveBandMemberAction
{
    public static function make(Band $band): DeleteAction
    {
        return DeleteAction::make()
            ->label('Remove')
            ->requiresConfirmation()
            ->modalHeading('Remove Band Member')
            ->modalDescription('Are you sure you want to remove this member from the band?')
            ->using(fn($record) => $record->delete())
            ->visible(
                fn($record): bool => Auth::user()->can('update', $band) &&
                    $record->user_id !== $band->owner_id // Can't remove owner
            );
    }
}
