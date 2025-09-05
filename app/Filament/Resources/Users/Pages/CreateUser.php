<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\UserInvitationService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Invite User';

    protected static ?string $breadcrumb = 'Invite';

    protected function handleRecordCreation(array $data): User
    {
        $invitationService = \UserInvitationService::getFacadeRoot();
        
        // Extract role names from the form data
        $roleNames = [];
        if (isset($data['roles']) && is_array($data['roles'])) {
            $roleNames = \Spatie\Permission\Models\Role::whereIn('id', $data['roles'])
                ->pluck('name')
                ->toArray();
        }

        // Use the invitation service to create and invite the user
        return $invitationService->inviteUser($data['email'], $roleNames);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'User invitation sent successfully';
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('Invitation sent')
            ->body("An invitation email has been sent to {$this->record->email}")
            ->success()
            ->send();
    }
}
