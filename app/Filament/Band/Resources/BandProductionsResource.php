<?php

namespace App\Filament\Band\Resources;

use App\Filament\Band\Resources\BandProductionsResource\Pages;
use CorvMC\Bands\Models\Band;
use CorvMC\Events\Models\Event;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;

class BandProductionsResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = Event::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Events';

    protected static ?string $modelLabel = 'Event';

    protected static ?string $pluralModelLabel = 'Events';

    protected static ?int $navigationSort = 30;

    protected static ?string $tenantOwnershipRelationshipName = 'performers';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    Split::make([
                        SpatieMediaLibraryImageColumn::make('poster')
                            ->collection('poster')
                            ->conversion('thumb')
                            ->circular()
                            ->grow(false)
                            ->size(60),
                        Stack::make([
                            TextColumn::make('title')
                                ->weight(FontWeight::Bold)
                                ->searchable(),
                            TextColumn::make('start_datetime')
                                ->label('Date')
                                ->dateTime('l, M j, Y @ g:i A')
                                ->color('gray'),
                        ]),
                        Stack::make([
                            TextColumn::make('status')
                                ->badge()
                                ->color(fn ($state) => match ($state?->value ?? $state) {
                                    'published' => 'success',
                                    'draft' => 'gray',
                                    'cancelled' => 'danger',
                                    'postponed' => 'warning',
                                    default => 'gray',
                                }),
                        ])->grow(false),
                    ]),
                ]),
            ])
            ->contentGrid([
                'default' => 1,
                'sm' => 1,
                'md' => 2,
            ])
            ->defaultSort('start_datetime', 'desc')
            ->recordUrl(fn (Event $record): string => URL::route('events.show', $record))
            ->emptyStateHeading('No events found')
            ->emptyStateDescription('Events where your band is performing will appear here.');
    }

    public static function getNavigationBadge(): ?string
    {
        /** @var Band|null $band */
        $band = Filament::getTenant();

        if (! $band) {
            return null;
        }

        $count = Event::forBand($band->id)
            ->where('start_datetime', '>', now())
            ->count();

        return $count ?: null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBandProductions::route('/'),
        ];
    }

    public static function canAccess(): bool
    {
        /** @var Band|null $tenant */
        $tenant = Filament::getTenant();

        return $tenant && auth()->user()?->can('view', $tenant);
    }
}
