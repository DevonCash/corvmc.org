<?php

namespace App\Filament\Resources\Bands\RelationManagers;

use App\Actions\Bands\AcceptBandInvitation;
use App\Actions\Bands\AddBandMember;
use App\Actions\Bands\CancelBandInvitation;
use App\Actions\Bands\DeclineBandInvitation;
use App\Actions\Bands\ReinviteBandMember;
use App\Actions\Bands\RemoveBandMember;
use App\Actions\Bands\ResendBandInvitation;
use App\Actions\Bands\UpdateBandMember;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = "Band Members";
    protected static ?string $recordTitleAttribute = 'name';

    public function canViewAny(): bool
    {
        // Allow viewing if user can view the parent record
        return User::me()?->can('view', $this->ownerRecord);
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
            ->headerActions([
                AddBandMember::filamentAction()
                    ->record($this->ownerRecord),
            ])
            ->recordActions([
                AcceptBandInvitation::filamentAction(),
                DeclineBandInvitation::filamentAction(),
                ResendBandInvitation::filamentAction(),
                ReinviteBandMember::filamentAction(),
                UpdateBandMember::filamentAction(),
                RemoveBandMember::filamentAction(),
                CancelBandInvitation::filamentAction(),
            ])
            ->defaultSort('created_at', 'asc');
    }
}
