<?php

namespace App\Filament\Shared\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Shows all invitations received by a User, across all invitable types.
 *
 * Attach to the UserResource to give staff a read-only view of a member's
 * invitation history (bands, events, rehearsals).
 */
class UserInvitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'receivedInvitations';

    protected static ?string $title = 'Invitations';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['invitable', 'inviter']))
            ->columns([
                TextColumn::make('invitable_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'band' => 'Band',
                        'event' => 'Event',
                        'rehearsal_reservation' => 'Rehearsal',
                        default => class_basename($state),
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'band' => 'info',
                        'event' => 'primary',
                        'rehearsal_reservation' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('invitable.name')
                    ->label('Subject')
                    ->getStateUsing(function ($record): string {
                        $invitable = $record->invitable;

                        if (! $invitable) {
                            return '(deleted)';
                        }

                        // Each invitable may expose a different name attribute
                        return $invitable->name
                            ?? $invitable->title
                            ?? $invitable->getDisplayTitle()
                            ?? "(#{$invitable->id})";
                    }),

                TextColumn::make('inviter.name')
                    ->label('From')
                    ->placeholder('Self'),

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

                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'accepted' => 'Accepted',
                        'declined' => 'Declined',
                    ]),
                SelectFilter::make('invitable_type')
                    ->label('Type')
                    ->options([
                        'band' => 'Band',
                        'event' => 'Event',
                        'rehearsal_reservation' => 'Rehearsal',
                    ]),
            ]);
    }
}
