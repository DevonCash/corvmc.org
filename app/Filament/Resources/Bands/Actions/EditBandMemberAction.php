<?php

namespace App\Filament\Resources\Bands\Actions;

use App\Models\Band;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Auth;

class EditBandMemberAction
{
    public static function make(Band $band): EditAction
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
                                fn($record): bool => ! Auth::user()->can('update', $band) ||
                                    $record->user_id === $band->owner_id
                            ),

                        TextInput::make('position')
                            ->label('Position')
                            ->placeholder('e.g., Lead Guitarist, Vocalist, Drummer')
                            ->default(fn($record) => $record->position)
                            ->maxLength(100),
                    ]),
            ])
            ->using(function ($record, array $data) use ($band): void {
                \App\Actions\Bands\UpdateBandMember::run(
                    $record,
                    $band,
                    $data
                );
            })
            ->visible(
                fn($record): bool => Auth::user()->can('update', $band) ||
                    $record->user_id === Auth::user()->id
            );
    }
}
