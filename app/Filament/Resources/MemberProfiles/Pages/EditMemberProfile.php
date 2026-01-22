<?php

namespace App\Filament\Resources\MemberProfiles\Pages;

use CorvMC\Membership\Actions\MemberProfiles\DeleteMemberProfile as DeleteMemberProfileAction;
use CorvMC\Membership\Actions\MemberProfiles\UpdateMemberProfile as UpdateMemberProfileAction;
use App\Filament\Resources\MemberProfiles\MemberProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMemberProfile extends EditRecord
{
    protected static string $resource = MemberProfileResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return UpdateMemberProfileAction::run($record, $data);
    }

    protected function handleRecordDeletion(Model $record): void
    {
        DeleteMemberProfileAction::run($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
