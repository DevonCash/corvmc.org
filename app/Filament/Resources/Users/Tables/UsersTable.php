<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('profile.avatar_url')
                    ->label('Avatar')
                    ->circular()
                    ->size(40)
                    ->defaultImageUrl('https://fastly.picsum.photos/id/1012/100/100.jpg?hmac=vuow0o9zubuAYNA_nZKuHb055Vy6pf6df8dUXl-6F2Y'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('profile.pronouns')
                    ->label('Pronouns')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('roles.name')
                    ->badge()
                    ->searchable()
                    ->color('primary'),
                TextColumn::make('profile.visibility')
                    ->label('Visibility')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'success',
                        'members' => 'warning',
                        'private' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('email_verified_at')
                    ->label('Verified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('reservations_count')
                    ->label('Reservations')
                    ->counts('reservations')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('transactions_count')
                    ->label('Transactions')
                    ->counts('transactions')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('band_profiles_count')
                    ->label('Bands')
                    ->counts('bandProfiles')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload(),
                TernaryFilter::make('email_verified_at')
                    ->label('Email Verified')
                    ->nullable(),
                SelectFilter::make('profile.visibility')
                    ->label('Profile Visibility')
                    ->options([
                        'public' => 'Public',
                        'members' => 'Members Only',
                        'private' => 'Private',
                    ]),
                TernaryFilter::make('sustaining_member')
                    ->label('Sustaining Member')
                    ->queries(
                        true: fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', 'sustaining member')),
                        false: fn ($query) => $query->whereDoesntHave('roles', fn ($q) => $q->where('name', 'sustaining member')),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
