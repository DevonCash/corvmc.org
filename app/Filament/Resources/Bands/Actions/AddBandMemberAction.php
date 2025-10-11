<?php

namespace App\Filament\Resources\Bands\Actions;

use App\Models\Band;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Auth;

class AddBandMemberAction
{
    public static function make(Band $band): Action
    {
        return Action::make('invite')
            ->label('Add Member')
            ->color('primary')
            ->icon('tabler-user-plus')
            ->modalWidth('xl')
            ->schema([
                TextInput::make('name')
                    ->label('Name')
                    ->placeholder('Member name or stage name')
                    ->maxLength(255)
                    ->required(fn($get) => !$get('user_id') && !$get('email'))
                    ->helperText(
                        fn($get) =>
                        $get('user_id') ? 'Override their CMC name for this band (optional)' : ($get('email') ? 'Their name for the band (required)' :
                            'This name will be displayed publicly (required for non-CMC members)')
                    ),

                Grid::make(2)->schema([
                    Select::make('user_id')
                        ->label('CMC Member (optional)')
                        ->getSearchResultsUsing(
                            fn(string $search): array => User::where(function ($query) use ($search) {
                                $query->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            })
                                ->whereDoesntHave('bandProfiles', fn($query) => $query->where('band_profile_id', $band->id))
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn($user) => [$user->id => "{$user->name} ({$user->email})"])
                                ->toArray()
                        )
                        ->getOptionLabelUsing(
                            fn($value): ?string => ($user = User::find($value)) ? "{$user->name} ({$user->email})" : null
                        )
                        ->searchable()
                        ->reactive()
                        ->afterStateUpdated(function ($state, $set, $get) {
                            if ($state) {
                                $user = User::find($state);
                                if ($user) {
                                    $set('name', $user->name);
                                }
                            }
                        })
                        ->helperText('Select an existing CMC member'),

                    TextInput::make('email')
                        ->label('Invite by Email (optional)')
                        ->email()
                        ->placeholder('invitee@example.com')
                        ->helperText('Invite someone new to join CMC and this band')
                        ->rule(function ($get) {
                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                if ($value && $get('user_id')) {
                                    $fail('Cannot select both an existing member and invite by email.');
                                }
                                if ($value && User::where('email', $value)->exists()) {
                                    $fail('This email is already registered. Please select them from the CMC Member dropdown instead.');
                                }
                            };
                        }),
                ]),

                Grid::make(4)
                    ->schema([
                        Select::make('role')
                            ->label('Role')
                            ->options([
                                'member' => 'Member',
                                'admin' => 'Admin',
                            ])
                            ->default('member')
                            ->required(),

                        TextInput::make('position')
                            ->columnSpan(3)
                            ->label('Position')
                            ->placeholder('e.g., Lead Guitarist, Vocalist, Drummer')
                            ->maxLength(100),
                    ]),
            ])
            ->action(function (array $data) use ($band): void {
                if ($data['user_id']) {
                    // Existing CMC member - send invitation
                    $user = User::find($data['user_id']);

                    try {
                        \App\Actions\Bands\InviteMember::run(
                            $band,
                            $user,
                            $data['role'],
                            $data['position'] ?? null,
                            $data['name'] ?? null
                        );

                        Notification::make()
                            ->title('Invitation sent')
                            ->body("Invitation sent to {$user->name}")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Cannot send invitation')
                            ->body($e->getMessage())
                            ->warning()
                            ->send();
                    }
                } elseif ($data['email']) {
                    // New user by email - create account and invite to band
                    try {
                        $user = \App\Actions\Bands\CreateUserAndInviteToBand::run(
                            $band,
                            $data['name'],
                            $data['email'],
                            $data['role'],
                            $data['position'] ?? null
                        );

                        Notification::make()
                            ->title('User created and invitation sent')
                            ->body("Created CMC account for {$user->email} and sent band invitation")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Cannot send invitation')
                            ->body($e->getMessage())
                            ->warning()
                            ->send();
                    }
                } else {
                    // Non-CMC member - add directly as active
                    \App\Actions\Bands\AddNonCMCMember::run(
                        $band,
                        $data['name'],
                        $data['role'],
                        $data['position'] ?? null
                    );

                    Notification::make()
                        ->title('Member added')
                        ->body("Added {$data['name']} to the band")
                        ->success()
                        ->send();
                }
            })
            ->visible(fn (): bool => Auth::user()->can('update', $band));
    }
}
