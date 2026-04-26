<?php

namespace App\Filament\Band\Resources;

use App\Filament\Actions\Bands\AcceptBandInvitationAction;
use App\Filament\Actions\Bands\CancelBandInvitationAction;
use App\Filament\Actions\Bands\DeclineBandInvitationAction;
use App\Filament\Actions\Bands\RemoveBandMemberAction;
use App\Filament\Actions\Bands\SendBandMemberInvitationAction;
use App\Filament\Actions\Bands\UpdateBandMemberAction;
use App\Filament\Band\Resources\BandMembersResource\Pages;
use CorvMC\Bands\Models\Band;
use CorvMC\Bands\Models\BandMember;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BandMembersResource extends Resource
{
    protected static ?string $model = BandMember::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Members';

    protected static ?string $modelLabel = 'Member';

    protected static ?string $pluralModelLabel = 'Members';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Member Name')
                    ->weight(FontWeight::Bold)
                    ->sortable(),

                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'info',
                        'owner' => 'primary',
                        default => 'gray',
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
                SendBandMemberInvitationAction::make(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBandMembers::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        /** @var Band|null $tenant */
        $tenant = Filament::getTenant();

        return $tenant && auth()->user()?->can('view', $tenant);
    }
}
