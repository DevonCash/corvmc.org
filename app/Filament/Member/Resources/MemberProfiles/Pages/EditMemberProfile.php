<?php

namespace App\Filament\Member\Resources\MemberProfiles\Pages;

use CorvMC\Membership\Facades\MemberProfileService;
use App\Filament\Member\Resources\MemberProfiles\MemberProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMemberProfile extends EditRecord
{
    protected static string $resource = MemberProfileResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return MemberProfileService::updateMemberProfile($record, $data);
    }

    protected function handleRecordDeletion(Model $record): void
    {
        MemberProfileService::deleteMemberProfile($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
