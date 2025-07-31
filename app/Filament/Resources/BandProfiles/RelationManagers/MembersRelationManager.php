<?php

namespace App\Filament\Resources\BandProfiles\RelationManagers;

use App\Models\User;
use App\Services\BandService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label('Photo')
                    ->circular()
                    ->size(40)
                    ->defaultImageUrl(function ($record) {
                        return 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=7F9CF5&background=EBF4FF&size=80';
                    }),

                TextColumn::make('display_name')
                    ->label('Member Name')
                    ->weight(FontWeight::Bold)
                    ->getStateUsing(fn ($record) => $record->pivot->name ?: $record->name)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('pivot.position')
                    ->label('Position')
                    ->placeholder('No position set')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('pivot.role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'success',
                        'member' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'admin' => 'Admin',
                        'member' => 'Member',
                        default => ucfirst($state),
                    }),

                TextColumn::make('pivot.status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'invited' => 'warning',
                        'declined' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Active',
                        'invited' => 'Pending Invitation',
                        'declined' => 'Declined',
                        default => ucfirst($state),
                    }),

                TextColumn::make('pivot.created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Member Status')
                    ->options([
                        'active' => 'Active Members',
                        'invited' => 'Pending Invitations',
                        'declined' => 'Declined Invitations',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value'])) {
                            return $query->wherePivot('status', $data['value']);
                        }
                        return $query;
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Member')
                    ->color('success')
                    ->form([
                        Grid::make(2)
                            ->schema([
                                Select::make('recordId')
                                    ->label('CMC Member (optional)')
                                    ->relationship('members', 'name')
                                    ->getSearchResultsUsing(fn (string $search): array => 
                                        User::where('name', 'like', "%{$search}%")
                                            ->whereDoesntHave('bandProfiles', fn ($query) => 
                                                $query->where('band_profile_id', $this->ownerRecord->id)
                                            )
                                            ->limit(50)
                                            ->pluck('name', 'id')
                                            ->toArray()
                                    )
                                    ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                                    ->searchable()
                                    ->helperText('Select a CMC member, or leave blank for non-CMC members'),

                                Select::make('role')
                                    ->label('Role')
                                    ->options([
                                        'member' => 'Member',
                                        'admin' => 'Admin',
                                    ])
                                    ->default('member')
                                    ->required(),
                            ]),

                        TextInput::make('name')
                            ->label('Display Name')
                            ->placeholder('Member name or stage name')
                            ->maxLength(255)
                            ->required()
                            ->helperText('This name will be displayed publicly'),

                        TextInput::make('position')
                            ->label('Position')
                            ->placeholder('e.g., Lead Guitarist, Vocalist, Drummer')
                            ->maxLength(100),
                    ])
                    ->using(function (array $data): void {
                        $bandService = app(BandService::class);
                        $user = $data['recordId'] ? User::find($data['recordId']) : null;
                        
                        if ($user) {
                            $bandService->addMember(
                                $this->ownerRecord,
                                $user,
                                $data['role'],
                                $data['position'] ?? null,
                                $data['name']
                            );
                        } else {
                            // For guest members (no CMC account)
                            $this->ownerRecord->members()->attach(null, [
                                'role' => $data['role'],
                                'position' => $data['position'] ?? null,
                                'name' => $data['name'],
                                'status' => 'active',
                            ]);
                        }
                    })
                    ->visible(fn (): bool => auth()->user()->can('manageMembers', $this->ownerRecord)),

                Action::make('invite')
                    ->label('Invite Member')
                    ->color('primary')
                    ->icon('heroicon-m-envelope')
                    ->form([
                        Select::make('user_id')
                            ->label('CMC Member')
                            ->relationship('members', 'name')
                            ->getSearchResultsUsing(fn (string $search): array => 
                                User::where('name', 'like', "%{$search}%")
                                    ->whereDoesntHave('bandProfiles', fn ($query) => 
                                        $query->where('band_profile_id', $this->ownerRecord->id)
                                    )
                                    ->limit(50)
                                    ->pluck('name', 'id')
                                    ->toArray()
                            )
                            ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                            ->searchable()
                            ->required()
                            ->helperText('Select a CMC member to invite'),

                        Grid::make(2)
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
                                    ->label('Position')
                                    ->placeholder('e.g., Lead Guitarist, Vocalist, Drummer')
                                    ->maxLength(100),
                            ]),

                        TextInput::make('name')
                            ->label('Display Name (optional)')
                            ->placeholder('Leave blank to use their CMC name')
                            ->maxLength(255)
                            ->helperText('Override their display name for this band'),
                    ])
                    ->action(function (array $data): void {
                        $bandService = app(BandService::class);
                        $user = User::find($data['user_id']);
                        
                        if ($user) {
                            $success = $bandService->inviteMember(
                                $this->ownerRecord,
                                $user,
                                $data['role'],
                                $data['position'] ?? null,
                                $data['name'] ?? null
                            );
                            
                            if ($success) {
                                Notification::make()
                                    ->title('Invitation sent')
                                    ->body("Invitation sent to {$user->name}")
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Cannot send invitation')
                                    ->body('User is already a member or has a pending invitation')
                                    ->warning()
                                    ->send();
                            }
                        }
                    })
                    ->visible(fn (): bool => auth()->user()->can('manageMembers', $this->ownerRecord)),
            ])
            ->actions([
                Action::make('accept_invitation')
                    ->label('Accept')
                    ->color('success')
                    ->icon('heroicon-m-check')
                    ->requiresConfirmation()
                    ->modalHeading('Accept Band Invitation')
                    ->modalDescription(fn ($record) => "Accept invitation to join {$this->ownerRecord->name}?")
                    ->action(function ($record): void {
                        $bandService = app(BandService::class);
                        $success = $bandService->acceptInvitation($this->ownerRecord, $record);
                        
                        if ($success) {
                            Notification::make()
                                ->title('Invitation accepted')
                                ->body("Welcome to {$this->ownerRecord->name}!")
                                ->success()
                                ->send();
                        }
                    })
                    ->visible(fn ($record): bool => 
                        $record->pivot->status === 'invited' && 
                        $record->id === auth()->id()
                    ),

                Action::make('decline_invitation')
                    ->label('Decline')
                    ->color('danger')
                    ->icon('heroicon-m-x-mark')
                    ->requiresConfirmation()
                    ->modalHeading('Decline Band Invitation')
                    ->modalDescription(fn ($record) => "Decline invitation to join {$this->ownerRecord->name}?")
                    ->action(function ($record): void {
                        $bandService = app(BandService::class);
                        $success = $bandService->declineInvitation($this->ownerRecord, $record);
                        
                        if ($success) {
                            Notification::make()
                                ->title('Invitation declined')
                                ->body('You have declined the invitation')
                                ->success()
                                ->send();
                        }
                    })
                    ->visible(fn ($record): bool => 
                        $record->pivot->status === 'invited' && 
                        $record->id === auth()->id()
                    ),

                Action::make('resend_invitation')
                    ->label('Resend')
                    ->color('warning')
                    ->icon('heroicon-m-arrow-path')
                    ->action(function ($record): void {
                        $bandService = app(BandService::class);
                        $success = $bandService->resendInvitation($this->ownerRecord, $record);
                        
                        if ($success) {
                            Notification::make()
                                ->title('Invitation resent')
                                ->body("Invitation resent to {$record->name}")
                                ->success()
                                ->send();
                        }
                    })
                    ->visible(fn ($record): bool => 
                        $record->pivot->status === 'invited' && 
                        auth()->user()->can('manageMembers', $this->ownerRecord)
                    ),

                EditAction::make()
                    ->form([
                        TextInput::make('name')
                            ->label('Display Name')
                            ->placeholder('Member name or stage name')
                            ->default(fn ($record) => $record->pivot->name)
                            ->maxLength(255)
                            ->required()
                            ->helperText('This name will be displayed publicly'),

                        Grid::make(2)
                            ->schema([
                                Select::make('role')
                                    ->label('Role')
                                    ->options([
                                        'member' => 'Member',
                                        'admin' => 'Admin',
                                    ])
                                    ->default(fn ($record) => $record->pivot->role)
                                    ->required()
                                    ->disabled(fn ($record): bool => 
                                        !auth()->user()->can('changeMemberRoles', $this->ownerRecord) ||
                                        $record->id === $this->ownerRecord->owner_id
                                    ),

                                TextInput::make('position')
                                    ->label('Position')
                                    ->placeholder('e.g., Lead Guitarist, Vocalist, Drummer')
                                    ->default(fn ($record) => $record->pivot->position)
                                    ->maxLength(100),
                            ]),
                    ])
                    ->using(function ($record, array $data): void {
                        $this->ownerRecord->members()->updateExistingPivot($record->id, [
                            'role' => $data['role'],
                            'position' => $data['position'] ?? null,
                            'name' => $data['name'],
                        ]);
                    })
                    ->visible(fn ($record): bool => 
                        auth()->user()->can('manageMembers', $this->ownerRecord) ||
                        $record->id === auth()->id()
                    ),

                DeleteAction::make()
                    ->label('Remove')
                    ->requiresConfirmation()
                    ->modalHeading('Remove Band Member')
                    ->modalDescription('Are you sure you want to remove this member from the band?')
                    ->using(fn ($record) => $this->ownerRecord->members()->detach($record->id))
                    ->visible(fn ($record): bool => 
                        auth()->user()->can('removeMembers', $this->ownerRecord) &&
                        $record->id !== $this->ownerRecord->owner_id // Can't remove owner
                    ),
            ])
            ->defaultSort('pivot_created_at', 'asc');
    }
}