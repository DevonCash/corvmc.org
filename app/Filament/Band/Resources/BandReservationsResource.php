<?php

namespace App\Filament\Band\Resources;

use CorvMC\SpaceManagement\Actions\Reservations\CancelReservation;
use App\Filament\Band\Resources\BandReservationsResource\Pages;
use App\Filament\Resources\Reservations\Schemas\ReservationInfolist;
use App\Filament\Resources\Reservations\Tables\Columns\ReservationColumns;
use App\Models\Band;
use App\Models\Reservation;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Table;

class BandReservationsResource extends Resource
{
    protected static ?string $tenantOwnershipRelationshipName = 'reservable';

    protected static ?string $model = Reservation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Reservations';

    protected static ?string $modelLabel = 'Reservation';

    protected static ?string $pluralModelLabel = 'Reservations';

    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        ReservationColumns::timeRange(),
                        Stack::make([
                            ReservationColumns::statusDisplay()->grow(false),
                            ReservationColumns::costDisplay()->grow(false),
                        ])->alignment(Alignment::End)->space(2),
                    ]),
                ]),
            ])
            ->contentGrid([
                'default' => 1,
                'sm' => 1,
                'md' => 2,
                'xl' => 3,
            ])
            ->defaultSort('reserved_at', 'desc')
            ->recordAction('view')
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->schema(fn (Schema $infolist) => ReservationInfolist::configure($infolist))
                    ->modalHeading(fn (Reservation $record): string => "Reservation #{$record->id}")
                    ->modalFooterActions([
                        CancelReservation::filamentAction(),
                    ]),
                ActionGroup::make([
                    CancelReservation::filamentAction(),
                ]),
            ])
            ->emptyStateHeading('No reservations found')
            ->emptyStateDescription('Book a practice space for your band.');
    }

    public static function getNavigationBadge(): ?string
    {
        /** @var Band|null $band */
        $band = Filament::getTenant();

        if (! $band) {
            return null;
        }

        $count = Reservation::where('reservable_type', Band::class)
            ->where('reservable_id', $band->id)
            ->where('status', '!=', 'cancelled')
            ->where('reserved_at', '>', now())
            ->count();

        return $count ?: null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBandReservations::route('/'),
            'create' => Pages\CreateBandReservation::route('/create'),
        ];
    }

    public static function canAccess(): bool
    {
        /** @var Band|null $tenant */
        $tenant = Filament::getTenant();

        return $tenant && auth()->user()?->can('view', $tenant);
    }

    public static function canCreate(): bool
    {
        /** @var Band|null $tenant */
        $tenant = Filament::getTenant();

        if (! $tenant) {
            return false;
        }

        // All active members can create reservations for the band
        return $tenant->owner_id === auth()->id()
            || $tenant->activeMembers()->where('user_id', auth()->id())->exists();
    }
}
