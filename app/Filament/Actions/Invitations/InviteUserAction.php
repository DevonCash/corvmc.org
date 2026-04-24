<?php

namespace App\Filament\Actions\Invitations;

use App\Services\InvitationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class InviteUserAction
{
    public static function make(): Action
    {
        return Action::make('invite_user')
            ->label('Invite Member')
            ->icon('tabler-mail-plus')
            ->color('primary')
            ->modalHeading('Invite a New Member')
            ->modalWidth('md')
            ->schema([
                TextInput::make('email')
                    ->label('Email Address')
                    ->email()
                    ->required()
                    ->placeholder('member@example.com'),

                Textarea::make('message')
                    ->label('Personal Message')
                    ->placeholder('Add a personal note to the invitation...')
                    ->maxLength(500)
                    ->rows(3),
            ])
            ->action(function (array $data) {
                $service = app(InvitationService::class);
                $service->inviteUser($data['email'], array_filter([
                    'message' => $data['message'] ?? null,
                ]));

                Notification::make()
                    ->title('Invitation sent')
                    ->body("An invitation has been sent to {$data['email']}.")
                    ->success()
                    ->send();
            });
    }
}
