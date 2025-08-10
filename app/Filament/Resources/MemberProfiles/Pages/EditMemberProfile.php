<?php

namespace App\Filament\Resources\MemberProfiles\Pages;

use App\Filament\Resources\MemberProfiles\MemberProfileResource;
use App\Settings\MemberDirectorySettings;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMemberProfile extends EditRecord
{
    protected static string $resource = MemberProfileResource::class;


    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $directoryFlags = $this->data['directory_flags'] ?? [];
        $settings = app(MemberDirectorySettings::class);
        $availableFlags = array_keys($settings->getAvailableFlags());

        // Remove all current flags that are in the available flags list
        foreach ($availableFlags as $flag) {
            $this->record->unFlag($flag);
        }

        // Add the selected flags
        foreach ($directoryFlags as $flag) {
            if (in_array($flag, $availableFlags)) {
                $this->record->flag($flag);
            }
        }
    }
}
