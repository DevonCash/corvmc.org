<?php

namespace App\Filament\Member\Resources\Bands\RelationManagers;

use App\Filament\Actions\Bands\AcceptBandInvitationAction;
use App\Filament\Actions\Bands\CancelBandInvitationAction;
use App\Filament\Actions\Bands\DeclineBandInvitationAction;
use App\Filament\Actions\Bands\RemoveBandMemberAction;
use App\Filament\Actions\Bands\SendBandMemberInvitationAction;
use App\Filament\Actions\Bands\UpdateBandMemberAction;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = 'Band Members';

    public function canViewAny(): bool
    {
        // Allow viewing if user can view the parent record
        return User::me()?->can('view', $this->ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Member Name')
                    ->weight(FontWeight::Bold)
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'invited' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Active',
                        'invited' => 'Pending Invitation',
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
            ->headerActions([
                SendBandMemberInvitationAction::make()
                    ->record($this->ownerRecord),
            ])
            ->recordActions([
                AcceptBandInvitationAction::make(),
                DeclineBandInvitationAction::make(),
                UpdateBandMemberAction::make(),
                RemoveBandMemberAction::make(),
                CancelBandInvitationAction::make(),
            ])
            ->defaultSort('created_at', 'asc');
    }
}
