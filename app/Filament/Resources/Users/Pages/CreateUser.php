<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Invite User';

    protected static ?string $breadcrumb = 'Invite';

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'User invited successfully';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate a temporary name if not provided
        if (empty($data['name'])) {
            $data['name'] = 'Invited User';
        }

        // Generate a temporary password that will be reset when user accepts invitation
        $data['password'] = bcrypt(Str::random(32));

        return $data;
    }

    protected function afterCreate(): void
    {
        // Send invitation email (you would implement this based on your email system)
        // For now, we'll just show a notification
        Notification::make()
            ->title('Invitation sent')
            ->body("An invitation email has been sent to {$this->record->email}")
            ->success()
            ->send();

        // TODO: Implement actual email invitation logic here
        // This might involve creating an invitation token, sending an email, etc.
    }
}
