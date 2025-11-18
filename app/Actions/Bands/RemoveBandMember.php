<?php

namespace App\Actions\Bands;

use App\Models\BandMember;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class RemoveBandMember
{
    use AsAction;

    public function handle(BandMember $member)
    {
        // Implementation for removing a band member
        $member->delete();
    }

    public static function filamentAction(): Action
    {
        return Action::make('remove_band_member')
            ->icon('tabler-trash')
            ->label('Remove')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Remove Band Member')
            ->modalDescription('Are you sure you want to remove this member from the band?')
            ->action(function (BandMember $member) {
                static::run($member);

                Notification::make()
                    ->title('Band member removed')
                    ->success()
                    ->send();
            })
            ->authorize('delete');
    }
}
