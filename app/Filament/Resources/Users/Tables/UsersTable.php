<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('profile.avatar_url')
                    ->label('Avatar')
                    ->circular()
                    ->imageSize(40)
                    ->defaultImageUrl('https://fastly.picsum.photos/id/1012/100/100.jpg?hmac=vuow0o9zubuAYNA_nZKuHb055Vy6pf6df8dUXl-6F2Y'),
                TextColumn::make('name')
                    ->description(fn($record) => $record->pronouns)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->icon(fn($record) => $record->email_verified_at ? 'heroicon-s-check-circle' : 'heroicon-o-x')
                    ->tooltip(fn($record) => $record->email_verified_at ? 'Verified on ' . $record->email_verified_at->format('M d, Y') : 'Email not verified')
                    ->iconPosition(IconPosition::After)
                    ->iconColor(fn($record) => $record->email_verified_at ? 'success' : 'danger')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('roles.name')
                    ->badge()
                    ->searchable()
                    ->color('primary'),
                TextColumn::make('reservations_count')
                    ->label('Reservations')
                    ->counts('reservations')
                    ->sortable()
                    ->toggleable(true, true),
                TextColumn::make('transactions_count')
                    ->label('Transactions')
                    ->counts('transactions')
                    ->toggleable(true, true)
                    ->sortable(),
                TextColumn::make('band_profiles_count')
                    ->label('Bands')
                    ->counts('bandProfiles')
                    ->sortable()
                    ->toggleable(true, true),
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
                        true: fn($query) => $query->whereHas('roles', fn($q) => $q->where('name', 'sustaining member')),
                        false: fn($query) => $query->whereDoesntHave('roles', fn($q) => $q->where('name', 'sustaining member')),
                    ),
            ])
            ->recordActions([
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
