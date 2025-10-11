<?php

namespace App\Filament\Resources\Bands\RelationManagers;

use App\Filament\Resources\Bands\Actions\AcceptBandInvitationAction;
use App\Filament\Resources\Bands\Actions\AddBandMemberAction;
use App\Filament\Resources\Bands\Actions\CancelBandInvitationAction;
use App\Filament\Resources\Bands\Actions\DeclineBandInvitationAction;
use App\Filament\Resources\Bands\Actions\EditBandMemberAction;
use App\Filament\Resources\Bands\Actions\ReinviteBandMemberAction;
use App\Filament\Resources\Bands\Actions\RemoveBandMemberAction;
use App\Filament\Resources\Bands\Actions\ResendBandInvitationAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
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
                AddBandMemberAction::make($this->ownerRecord),
            ])
            ->recordActions([
                AcceptBandInvitationAction::make($this->ownerRecord),
                DeclineBandInvitationAction::make($this->ownerRecord),
                ResendBandInvitationAction::make($this->ownerRecord),
                CancelBandInvitationAction::make($this->ownerRecord),
                ReinviteBandMemberAction::make($this->ownerRecord),
                EditBandMemberAction::make($this->ownerRecord),
                RemoveBandMemberAction::make($this->ownerRecord),
            ])
            ->defaultSort('created_at', 'asc');
    }
}
