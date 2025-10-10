<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Users\CreateUser as CreateUserAction;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Invite User';

    protected static ?string $breadcrumb = 'Invite';

    protected function handleRecordCreation(array $data): Model
    {
        return CreateUserAction::run($data);
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
