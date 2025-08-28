<?php

namespace App\Filament\Actions;

use App\Services\UserInvitationService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Spatie\Permission\Models\Role;

class InviteUserAction
{
    public static function make(): Action
    {
        return Action::make('invite_user')
            ->label('Invite')
            ->icon('tabler-user-plus')
            ->color('primary')
            ->outlined()
            ->schema([
                TextInput::make('email')
                    ->label('Email Address')
                    ->email()
                    ->required()
                    ->unique('users', 'email')
                    ->placeholder('Enter email address'),

                CheckboxList::make('roles')
                    ->visible(fn() => auth()->user()->hasRole('admin'))
                    ->label('Assign Roles')
                    ->options(Role::all()->pluck('name', 'id'))
                    ->descriptions(Role::all()->pluck('name', 'id')->map(fn($name) => "Assign {$name} role"))
                    ->columns(2),
            ])
            ->modalHeading('Invite New User')
            ->modalDescription('Send an invitation email to a new user to join the CMC.')
            ->modalSubmitActionLabel('Send Invitation')
            ->modalWidth('md')
            ->action(function (array $data) {
                $invitationService = app(UserInvitationService::class);

                // Extract role names from the form data
                $roleNames = [];
                if (isset($data['roles']) && is_array($data['roles'])) {
                    $roleNames = Role::whereIn('id', $data['roles'])
                        ->pluck('name')
                        ->toArray();
                }

                // Use the invitation service to create and invite the user
                $user = $invitationService->inviteUser($data['email'], $roleNames);

                Notification::make()
                    ->title('Invitation sent')
                    ->body("An invitation email has been sent to {$user->email}")
                    ->success()
                    ->send();
            });
    }
}
