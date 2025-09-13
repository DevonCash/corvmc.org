<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Traits\HasCrudService;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use HasCrudService;

    protected static string $resource = UserResource::class;
    protected static ?string $crudService = 'UserService';

    protected static ?string $title = 'Invite User';

    protected static ?string $breadcrumb = 'Invite';

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
