<?php

namespace App\Actions\Bands;

use App\Actions\Invitations\ResendInvitation;
use App\Concerns\AsFilamentAction;
use App\Models\Band;
use App\Models\BandMember;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateBandMember
{
    use AsAction;
    use AsFilamentAction;

    /**
     * Update a band member with flexible user/non-user handling.
     */
    public function handle(
        BandMember $member,
        Band $band,
        array $data
    ): BandMember {
        $updateData = [
            'role' => $data['role'] ?? $member->role,
            'position' => $data['position'] ?? null,
            'name' => $data['name'],
        ];

        if (isset($data['user_id']) && $data['user_id']) {
            // Existing CMC member selected
            $updateData['user_id'] = $data['user_id'];

            // If changing from non-CMC to CMC, convert to invitation
            if (! $member->user_id && $member->status === 'active') {
                $updateData['status'] = 'invited';
                $updateData['invited_at'] = now();

                $user = User::find($data['user_id']);
                if ($user) {
                    ResendInvitation::run($band, $user);
                }
            }
        } elseif (isset($data['email']) && $data['email']) {
            // Email invitation - create new user
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt(Str::random(32)),
            ]);

            $updateData['user_id'] = $user->id;
            $updateData['status'] = 'invited';
            $updateData['invited_at'] = now();

            ResendInvitation::run($band, $user);
        } else {
            // No user association - keep as non-CMC member
            $updateData['user_id'] = null;
            // If converting from CMC to non-CMC, make them active
            if ($member->user_id) {
                $updateData['status'] = 'active';
                $updateData['invited_at'] = null;
            }
        }

        $member->update($updateData);

        return $member->fresh();
    }

    public static function filamentAction(): Action
    {
        return static::buildBaseAction()
            ->label('Edit')
            ->icon('tabler-edit')
            ->schema([
                TextInput::make('name')
                    ->label('Display Name')
                    ->placeholder('Member name or stage name')
                    ->default(fn ($record) => $record->name)
                    ->maxLength(255)
                    ->required()
                    ->helperText('This name will be displayed publicly'),

                Grid::make(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('CMC Member Account (optional)')
                            ->getSearchResultsUsing(
                                fn (string $search, $record): array => User::where(function ($query) use ($search) {
                                    $query->where('name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%");
                                })
                                    ->whereDoesntHave('bands', function ($query) use ($record) {
                                        return $query->where('band_profile_id', $record->id);
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn ($user) => [$user->id => "{$user->name} ({$user->email})"])
                                    ->toArray()
                            )
                            ->getOptionLabelUsing(
                                fn ($value): ?string => ($user = User::find($value)) ? "{$user->name} ({$user->email})" : null
                            )
                            ->searchable()
                            ->default(fn ($record) => $record->user_id)
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                if ($state && ! $get('name_manually_changed')) {
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
                            ->helperText('Invite someone new to join CMC')
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

                Grid::make(2)
                    ->schema([
                        Select::make('role')
                            ->label('Role')
                            ->options([
                                'member' => 'Member',
                                'admin' => 'Admin',
                            ])
                            ->default(fn ($record) => $record->role)
                            ->required()
                            ->disabled(
                                fn ($record): bool => ! User::me()?->can('update', $record) ||
                                    $record->user_id === $record->band->owner_id
                            ),

                        TextInput::make('position')
                            ->label('Position')
                            ->placeholder('e.g., Lead Guitarist, Vocalist, Drummer')
                            ->default(fn ($record) => $record->position)
                            ->maxLength(100),
                    ]),
            ])
            ->action(function ($record, array $data): void {
                static::run(
                    $record,
                    $record->band,
                    $data
                );

                Notification::make()
                    ->title('Band member updated')
                    ->success()
                    ->send();
            })
            ->authorize('update');
    }
}
