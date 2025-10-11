<?php

namespace App\Filament\Resources\Bands\RelationManagers;

use App\Models\User;
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
use Illuminate\Support\Facades\Auth;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = "Band Members";
    protected static ?string $recordTitleAttribute = 'name';

    public function canViewAny(): bool
    {
        // Allow viewing if user can view the parent record
        return Auth::user()->can('view', $this->ownerRecord);
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
                        \App\Actions\Bands\InviteMember::run(
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
                    try {
                        $user = \App\Actions\Bands\CreateUserAndInviteToBand::run(
                            $this->ownerRecord,
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
                        $this->ownerRecord,
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
                                fn($record): bool => ! Auth::user()->can('changeMemberRoles', $this->ownerRecord) ||
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
                \App\Actions\Bands\UpdateBandMember::run(
                    $record,
                    $this->ownerRecord,
                    $data
                );
            })
            ->visible(
                fn($record): bool => Auth::user()->can('manageMembers', $this->ownerRecord) ||
                    $record->user_id === Auth::user()->id
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
                    ->visible(fn(): bool => Auth::user()->can('manageMembers', $this->ownerRecord))

            ])
            ->recordActions([
                Action::make('accept_invitation')
                    ->label('Accept')
                    ->color('success')
                    ->icon('tabler-check')
                    ->requiresConfirmation()
                    ->modalHeading('Accept Band Invitation')
                    ->modalDescription(fn($record) => "Accept invitation to join {$this->ownerRecord->name}?")
                    ->action(function ($record): void {
                        $user = User::find($record->user_id);

                        try {
                            \App\Actions\Bands\AcceptInvitation::run($this->ownerRecord, $user);

                            Notification::make()
                                ->title('Invitation accepted')
                                ->body("Welcome to {$this->ownerRecord->name}!")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to accept invitation')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(
                        fn($record): bool => $record->status === 'invited' &&
                            $record->user_id === Auth::user()->id
                    ),

                Action::make('decline_invitation')
                    ->label('Decline')
                    ->color('danger')
                    ->icon('tabler-x')
                    ->requiresConfirmation()
                    ->modalHeading('Decline Band Invitation')
                    ->modalDescription(fn($record) => "Decline invitation to join {$this->ownerRecord->name}?")
                    ->action(function ($record): void {
                        $user = User::find($record->user_id);

                        try {
                            \App\Actions\Bands\DeclineInvitation::run($this->ownerRecord, $user);

                            Notification::make()
                                ->title('Invitation declined')
                                ->body('You have declined the invitation')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to decline invitation')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(
                        fn($record): bool => $record->status === 'invited' &&
                            $record->user_id === Auth::user()->id
                    ),

                Action::make('resend_invitation')
                    ->label('Resend')
                    ->color('warning')
                    ->icon('tabler-mail-forward')
                    ->action(function ($record): void {
                        $user = User::find($record->user_id);

                        \App\Actions\Bands\ResendInvitation::run($this->ownerRecord, $user);

                        Notification::make()
                            ->title('Invitation resent')
                            ->body("Invitation resent to {$record->name}")
                            ->success()
                            ->send();
                    })
                    ->visible(
                        fn($record): bool => $record->status === 'invited' &&
                            Auth::user()->can('manageMembers', $this->ownerRecord)
                    ),

                Action::make('reinvite_declined')
                    ->label('Re-invite')
                    ->color('primary')
                    ->icon('tabler-mail')
                    ->requiresConfirmation()
                    ->modalHeading('Re-invite Member')
                    ->modalDescription(fn($record) => "Send a new invitation to {$record->name}?")
                    ->action(function ($record): void {
                        $user = User::find($record->user_id);

                        try {
                            \App\Actions\Bands\InviteMember::run(
                                $this->ownerRecord,
                                $user,
                                $record->role ?? 'member',
                                $record->position,
                                $record->name
                            );

                            Notification::make()
                                ->title('Invitation sent')
                                ->body("New invitation sent to {$record->name}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Failed to send invitation')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(
                        fn($record): bool => $record->status === 'declined' &&
                            Auth::user()->can('manageMembers', $this->ownerRecord)
                    ),

                $this->makeEditMember(),

                DeleteAction::make()
                    ->label('Remove')
                    ->requiresConfirmation()
                    ->modalHeading('Remove Band Member')
                    ->modalDescription('Are you sure you want to remove this member from the band?')
                    ->using(fn($record) => $record->delete())
                    ->visible(
                        fn($record): bool => Auth::user()->can('removeMembers', $this->ownerRecord) &&
                            $record->user_id !== $this->ownerRecord->owner_id // Can't remove owner
                    ),
            ])
            ->defaultSort('created_at', 'asc');
    }
}
