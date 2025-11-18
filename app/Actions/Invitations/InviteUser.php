<?php

namespace App\Actions\Invitations;

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\UserInvitationNotification;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Permission\Models\Role;

class InviteUser
{
    use AsAction;

    public function handle(string $email, array $data = []): Invitation
    {
        return DB::transaction(function () use ($email, $data) {
            $invitation = GenerateInvitation::run($email, $data);

            Notification::route('mail', $email)
                ->notify(new UserInvitationNotification($invitation, $data));

            $invitation->markAsSent();

            return $invitation;
        });
    }

    public static function filamentAction(): Action
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
                    ->visible(fn () => User::me()->hasRole('admin'))
                    ->label('Assign Roles')
                    ->options(Role::all()->pluck('name', 'id'))
                    ->descriptions(Role::all()->pluck('name', 'id')->map(fn ($name) => "Assign {$name} role"))
                    ->columns(2),
            ])
            ->modalHeading('Invite New User')
            ->modalDescription('Send an invitation email to a new user to join the CMC.')
            ->modalSubmitActionLabel('Send Invitation')
            ->modalWidth('md')
            ->action(function (array $data) {
                // Extract role names from the form data
                $roleNames = [];
                if (isset($data['roles']) && is_array($data['roles'])) {
                    $roleNames = Role::whereIn('id', $data['roles'])
                        ->pluck('name')
                        ->toArray();
                }

                // Use the invitation action to create and invite the user
                $invitation = static::run($data['email'], ['roles' => $roleNames]);

                FilamentNotification::make()
                    ->title('Invitation sent')
                    ->body("An invitation email has been sent to {$invitation->email}")
                    ->success()
                    ->send();
            });
    }
}
