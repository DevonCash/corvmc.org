<?php

namespace App\Filament\Resources\Bands\RelationManagers;

use App\Models\User;
use App\Facades\BandService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'allMembers';

    protected static ?string $recordTitleAttribute = 'name';

    public function canViewAny(): bool
    {
        // Allow viewing if user can view the parent record
        return auth()->user()->can('view', $this->ownerRecord);
    }

    public function makeAddMember(): Action
    {
        return Action::make('invite')
            ->label('Add Member')
            ->color('primary')
            ->icon('tabler-user-plus')
            ->modalWidth('xl')
            ->schema([
                TextInput::make('name')
                    ->label('Display Name')
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
                                ->whereDoesntHave('bandProfiles', fn($query) => $query->where('band_profile_id', $this->ownerRecord->id))
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
            ->action(function (array $data): void {

                if ($data['user_id']) {
                    // Existing CMC member - send invitation
                    $user = User::find($data['user_id']);
                    try {
                        BandService::inviteMember(
                            $this->ownerRecord,
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
                    $user = User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'password' => bcrypt(Str::random(32)), // Temporary password, they'll set it via invitation
                    ]);

                    try {
                        BandService::inviteMember(
                            $this->ownerRecord,
                            $user,
                            $data['role'],
                            $data['position'] ?? null,
                            $data['name']
                        );

                        Notification::make()
                            ->title('User created and invitation sent')
                            ->body("Created CMC account for {$user->email} and sent band invitation")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error creating user or sending invitation')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                } else {
                    // Non-CMC member - add directly as active
                    DB::table('band_profile_members')->insert([
                        'band_profile_id' => $this->ownerRecord->id,
                        'user_id' => null,
                        'role' => $data['role'],
                        'position' => $data['position'] ?? null,
                        'name' => $data['name'],
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Member added')
                        ->body("Added {$data['name']} to the band")
                        ->success()
                        ->send();
                }
            });
    }

    public function makeEditMember(): EditAction
    {
        return EditAction::make()
            ->schema([
                TextInput::make('name')
                    ->label('Display Name')
                    ->placeholder('Member name or stage name')
                    ->default(fn($record) => $record->name)
                    ->maxLength(255)
                    ->required()
                    ->helperText('This name will be displayed publicly'),

                Grid::make(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('CMC Member Account (optional)')
                            ->getSearchResultsUsing(
                                fn(string $search): array => User::where(function ($query) use ($search) {
                                    $query->where('name', 'like', "%{$search}%")
                                        ->orWhere('email', 'like', "%{$search}%");
                                })
                                    ->whereDoesntHave('bandProfiles', fn($query) => $query->where('band_profile_id', $this->ownerRecord->id))
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($user) => [$user->id => "{$user->name} ({$user->email})"])
                                    ->toArray()
                            )
                            ->getOptionLabelUsing(
                                fn($value): ?string => ($user = User::find($value)) ? "{$user->name} ({$user->email})" : null
                            )
                            ->searchable()
                            ->default(fn($record) => $record->user_id)
                            ->reactive()
                            ->afterStateUpdated(function ($state, $set, $get) {
                                if ($state && !$get('name_manually_changed')) {
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
                            ->default(fn($record) => $record->role)
                            ->required()
                            ->disabled(
                                fn($record): bool => ! auth()->user()->can('changeMemberRoles', $this->ownerRecord) ||
                                    $record->user_id === $this->ownerRecord->owner_id
                            ),

                        TextInput::make('position')
                            ->label('Position')
                            ->placeholder('e.g., Lead Guitarist, Vocalist, Drummer')
                            ->default(fn($record) => $record->position)
                            ->maxLength(100),
                    ]),
            ])
            ->using(function ($record, array $data): void {
                $updateData = [
                    'role' => $data['role'] ?? $record->role,
                    'position' => $data['position'] ?? null,
                    'name' => $data['name'],
                ];

                if ($data['user_id']) {
                    // Existing CMC member selected
                    $updateData['user_id'] = $data['user_id'];

                    // If changing from non-CMC to CMC, convert to invitation
                    if (!$record->user_id && $record->status === 'active') {
                        $updateData['status'] = 'invited';
                        $updateData['invited_at'] = now();

                        $user = User::find($data['user_id']);
                        if ($user) {
                            BandService::resendInvitation($this->ownerRecord, $user);
                        }
                    }
                } elseif ($data['email']) {
                    // Email invitation - create new user
                    $user = User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'password' => bcrypt(Str::random(32)),
                    ]);

                    $updateData['user_id'] = $user->id;
                    $updateData['status'] = 'invited';
                    $updateData['invited_at'] = now();

                    BandService::resendInvitation($this->ownerRecord, $user);
                } else {
                    // No user association - keep as non-CMC member
                    $updateData['user_id'] = null;
                    // If converting from CMC to non-CMC, make them active
                    if ($record->user_id) {
                        $updateData['status'] = 'active';
                        $updateData['invited_at'] = null;
                    }
                }

                $record->update($updateData);
            })
            ->visible(
                fn($record): bool => auth()->user()->can('manageMembers', $this->ownerRecord) ||
                    $record->user_id === auth()->id()
            );
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('display_name')
                    ->label('Member Name')
                    ->weight(FontWeight::Bold)
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'invited' => 'warning',
                        'declined' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'active' => 'Active',
                        'invited' => 'Pending Invitation',
                        'declined' => 'Declined',
                        default => ucfirst($state),
                    }),

                TextColumn::make('position')
                    ->label('Position')
                    ->placeholder('No position set')
                    ->grow(true),

                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('show_declined')
                    ->toggle()
                    ->query(function (Builder $query, array $data): Builder {
                        if (!($data['show_declined'] ?? false)) {
                            return $query->whereNot('status', 'declined');
                        }
                        return $query;
                    })

            ])
            ->filtersLayout(FiltersLayout::BelowContent)
            ->headerActions([
                $this->makeAddMember()
                    ->visible(fn(): bool => auth()->user()->can('manageMembers', $this->ownerRecord))

            ])
            ->recordActions([
                Action::make('accept_invitation')
                    ->label('Accept')
                    ->color('success')
                    ->icon('heroicon-m-check')
                    ->requiresConfirmation()
                    ->modalHeading('Accept Band Invitation')
                    ->modalDescription(fn($record) => "Accept invitation to join {$this->ownerRecord->name}?")
                    ->action(function ($record): void {
                        try {
                            BandService::acceptInvitation($this->ownerRecord, $record);

                            Notification::make()
                                ->title('Invitation accepted')
                                ->body("Welcome to {$this->ownerRecord->name}!")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error accepting invitation')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(
                        fn($record): bool => $record->status === 'invited' &&
                            $record->user_id === auth()->id()
                    ),

                Action::make('decline_invitation')
                    ->label('Decline')
                    ->color('danger')
                    ->icon('heroicon-m-x-mark')
                    ->requiresConfirmation()
                    ->modalHeading('Decline Band Invitation')
                    ->modalDescription(fn($record) => "Decline invitation to join {$this->ownerRecord->name}?")
                    ->action(function ($record): void {
                        try {
                            BandService::declineInvitation($this->ownerRecord, $record);

                            Notification::make()
                                ->title('Invitation declined')
                                ->body('You have declined the invitation')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error declining invitation')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(
                        fn($record): bool => $record->status === 'invited' &&
                            $record->user_id === auth()->id()
                    ),

                Action::make('resend_invitation')
                    ->label('Resend')
                    ->color('warning')
                    ->icon('heroicon-m-arrow-path')
                    ->action(function ($record): void {
                        BandService::resendInvitation($this->ownerRecord, $record);

                        Notification::make()
                            ->title('Invitation resent')
                            ->body("Invitation resent to {$record->name}")
                            ->success()
                            ->send();
                    })
                    ->visible(
                        fn($record): bool => $record->status === 'invited' &&
                            auth()->user()->can('manageMembers', $this->ownerRecord)
                    ),

                Action::make('reinvite_declined')
                    ->label('Re-invite')
                    ->color('primary')
                    ->icon('heroicon-m-envelope')
                    ->requiresConfirmation()
                    ->modalHeading('Re-invite Member')
                    ->modalDescription(fn($record) => "Send a new invitation to {$record->name}?")
                    ->action(function ($record): void {
                        BandService::inviteMember($this->ownerRecord, $record);

                        Notification::make()
                            ->title('Invitation sent')
                            ->body("New invitation sent to {$record->name}")
                            ->success()
                            ->send();
                    })
                    ->visible(
                        fn($record): bool => $record->status === 'declined' &&
                            auth()->user()->can('manageMembers', $this->ownerRecord)
                    ),

                $this->makeEditMember(),

                DeleteAction::make()
                    ->label('Remove')
                    ->requiresConfirmation()
                    ->modalHeading('Remove Band Member')
                    ->modalDescription('Are you sure you want to remove this member from the band?')
                    ->using(fn($record) => $record->delete())
                    ->visible(
                        fn($record): bool => auth()->user()->can('removeMembers', $this->ownerRecord) &&
                            $record->user_id !== $this->ownerRecord->owner_id // Can't remove owner
                    ),
            ])
            ->defaultSort('created_at', 'asc');
    }
}
