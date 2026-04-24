<?php

namespace App\Filament\Actions\Bands;

use App\Models\User;
use CorvMC\Bands\Models\Band;
use CorvMC\Membership\Services\BandService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament Action for adding a member to a band.
 *
 * This action handles the UI concerns for band member addition
 * and delegates business logic to the BandService.
 */
class SendBandMemberInvitationAction
{
    public static function make(): Action
    {
        return Action::make('add_member')
            ->label('Add Member')
            ->icon('tabler-user-plus')
            ->color('primary')
            ->requiresConfirmation(false)
            ->modalHeading('Add Band Member')
            ->modalWidth('md')
            ->visible(function (?Model $record) {
                if (!$record instanceof Band) {
                    return false;
                }

                $user = User::me();
                if (!$user) {
                    return false;
                }

                // Only band owner or admin can add members
                return $record->isOwner($user) || $record->isAdmin($user);
            })
            ->schema([
                Section::make()
                    ->schema([
                        Select::make('user_id')
                            ->label('Select Member')
                            ->searchable()
                            ->required()
                            ->getSearchResultsUsing(function (string $search, ?Model $record) {
                                if (!$record instanceof Band) {
                                    return [];
                                }

                                // Get existing member IDs to exclude from search
                                $existingMemberIds = $record->members()->pluck('users.id')->toArray();

                                return User::query()
                                    ->where(function ($query) use ($search) {
                                        $query->where('name', 'like', "%{$search}%")
                                            ->orWhere('email', 'like', "%{$search}%");
                                    })
                                    ->whereNotIn('id', $existingMemberIds)
                                    ->limit(50)
                                    ->get()
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(fn($value): ?string => User::find($value)?->name)
                            ->helperText('Search by name or email'),

                        Select::make('role')
                            ->label('Role')
                            ->options([
                                'member' => 'Member',
                                'admin' => 'Admin',
                            ])
                            ->default('member')
                            ->required()
                            ->helperText('Admins can manage band settings and members'),

                        TextInput::make('position')
                            ->label('Position/Instrument')
                            ->placeholder('e.g., Lead Guitar, Vocals, Drums')
                            ->maxLength(255)
                            ->helperText('Optional: What they do in the band'),
                    ]),
            ])
            ->action(function (array $data, Model $record) {
                if (!$record instanceof Band) {
                    return;
                }

                $user = User::find($data['user_id']);
                if (!$user) {
                    Notification::make()
                        ->title('User not found')
                        ->danger()
                        ->send();
                    return;
                }

                // Check if user is already a member
                if ($record->members->contains($user)) {
                    Notification::make()
                        ->title('User is already a member')
                        ->warning()
                        ->send();
                    return;
                }

                app(BandService::class)->inviteMember(
                    $record,
                    $user,
                    $data['role'],
                    $data['position'] ?? null,
                );

                // TODO: Send invitation notification to the user

                Notification::make()
                    ->title('Member invited successfully')
                    ->body("An invitation has been sent to {$user->name}.")
                    ->success()
                    ->send();
            });
    }
}
