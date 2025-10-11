<?php

namespace App\Filament\Resources\Productions\Actions;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

class InviteBandOwnerAction
{
    public static function make(): Action
    {
        return Action::make('invite_band_owner')
            ->label('Invite Owner')
            ->icon('tabler-user-plus')
            ->color('success')
            ->visible(fn($record) => !$record->owner_id) // Only show for bands without owners (touring bands)
            ->schema([
                TextInput::make('email')
                    ->label('Band Owner Email')
                    ->email()
                    ->required()
                    ->placeholder('bandleader@example.com')
                    ->helperText('We\'ll invite this person to join CMC and take ownership of this band.'),

                TextInput::make('hometown')
                    ->label('Update Band Location (Optional)')
                    ->placeholder('City, State/Country')
                    ->helperText('Refine the band\'s location if needed.'),

                Textarea::make('bio')
                    ->label('Update Band Bio (Optional)')
                    ->rows(3)
                    ->helperText('Add or refine the band\'s biography.'),
            ])
            ->action(function ($record, array $data) {

                // Prepare band data for updates
                $bandData = [];
                if (!empty($data['hometown'])) {
                    $bandData['hometown'] = $data['hometown'];
                }
                if (!empty($data['bio'])) {
                    $bandData['bio'] = $data['bio'];
                }

                try {
                    $invitation = \App\Actions\Invitations\InviteUserWithBand::run(
                        $data['email'],
                        $record->name,
                        $bandData
                    );

                    // Add any updated band data
                    if (!empty($bandData)) {
                        $record->update($bandData);
                    }

                    Notification::make()
                        ->title('Band Owner Invited!')
                        ->body("Invitation sent to {$data['email']} to own {$record->name}")
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('Error')
                        ->body('Failed to invite band owner: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->modalHeading(fn($record) => "Invite Owner for {$record->name}")
            ->modalDescription('Invite someone to join CMC and take ownership of this band profile.')
            ->modalSubmitActionLabel('Send Invitation');
    }
}
