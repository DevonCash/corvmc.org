<?php

namespace App\Filament\Shared\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Shows invitations for an InvitationSubject (Event, RehearsalReservation, etc.).
 *
 * Attach to any resource whose model uses the HasInvitations trait.
 */
class InvitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'invitations';

    protected static ?string $title = 'RSVPs';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'inviter']))
            ->columns([
                TextColumn::make('user.name')
                    ->label('Member')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('inviter.name')
                    ->label('Invited By')
                    ->placeholder('Self')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'accepted' => 'success',
                        'declined' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('data.comment')
                    ->label('Comment')
                    ->placeholder('—')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('responded_at')
                    ->label('Responded')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Invited')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'accepted' => 'Accepted',
                        'declined' => 'Declined',
                    ]),
            ]);
    }
}
