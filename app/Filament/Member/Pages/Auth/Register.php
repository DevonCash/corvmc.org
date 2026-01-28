<?php

namespace App\Filament\Member\Pages\Auth;

use App\Models\Invitation;
use App\Rules\NotSpamEmail;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;

/**
 * @method makeForm()
 */
class Register extends BaseRegister
{
    protected ?string $invitationToken = null;

    public function mount(): void
    {
        parent::mount();

        // Check for invitation token in request
        $this->invitationToken = request()->query('invitation');

        if ($this->invitationToken) {
            $invitation = \App\Actions\Invitations\FindInvitationByToken::run($this->invitationToken);

            if ($invitation && ! $invitation->isExpired() && ! $invitation->isUsed()) {
                // Prefill email from invitation
                $this->form->fill([
                    'email' => $invitation->email,
                ]);
            } else {
                // Clear invalid token
                $this->invitationToken = null;
            }
        } else {
            // Check for email query parameter (direct from redirect)
            $email = request()->query('email');
            if ($email) {
                $this->form->fill(['email' => $email]);
            }
        }
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('filament-panels::auth/pages/register.form.email.label'))
            ->email()
            ->required()
            ->maxLength(255)
            ->unique($this->getUserModel())
            ->disabled(fn () => ! empty($this->invitationToken)) // Disable if from invitation
            ->dehydrated()
            ->rule(new NotSpamEmail());
    }

    protected function handleRegistration(array $data): Model
    {
        $user = parent::handleRegistration($data);

        // If this was from an invitation, mark it as used
        if ($this->invitationToken) {
            $invitation = Invitation::withoutGlobalScopes()
                ->where('token', $this->invitationToken)
                ->first();

            if ($invitation) {
                $invitation->markAsUsed();

                // Handle band ownership if invitation includes band data
                if (isset($invitation->data['band_id'])) {
                    \App\Actions\Invitations\ConfirmBandOwnership::run($user, $invitation);
                }
            }
        }

        return $user;
    }
}
