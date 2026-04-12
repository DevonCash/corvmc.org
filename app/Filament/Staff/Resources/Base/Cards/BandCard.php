<?php

namespace App\Filament\Staff\Resources\Base\Cards;

use App\Filament\Staff\Resources\Bands\BandResource;
use CorvMC\Bands\Models\Band;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;

class BandCard
{
    /**
     * Create a standardized band card section
     */
    public static function make(string $relationship = 'band', ?string $label = null): Section
    {
        return Section::make($label ?? 'Band')
            ->icon('tabler-users-group')
            ->compact()
            ->headerActions([
                \Filament\Actions\Action::make('view_band')
                    ->hiddenLabel()
                    ->icon('tabler-arrow-right')
                    ->iconButton()
                    ->color('gray')
                    ->url(fn(?Model $record) => $record?->{$relationship} ? BandResource::getUrl('edit', ['record' => $record->{$relationship}]) : null)
                    ->openUrlInNewTab(),
            ])
            ->schema([
                Flex::make([
                    ImageEntry::make("{$relationship}.avatar")
                        ->hiddenLabel()
                        ->circular()
                        ->size(60)
                        ->grow(false)
                        ->defaultImageUrl(fn(?Model $record) => 'https://ui-avatars.com/api/?name=' . urlencode($record?->{$relationship}?->name ?? 'Unknown')),
                    Grid::make(1)
                        ->schema([
                            TextEntry::make("{$relationship}.name")
                                ->hiddenLabel()
                                ->weight('bold')
                                ->size('large'),
                            TextEntry::make("{$relationship}.genres")
                                ->hiddenLabel()
                                ->badge()
                                ->color('gray')
                                ->separator(' ')
                                ->limit(3),
                            TextEntry::make("{$relationship}.member_count")
                                ->hiddenLabel()
                                ->icon('tabler-users')
                                ->iconColor('gray')
                                ->state(fn(?Model $record) => $record?->{$relationship} ? $record->{$relationship}->members()->count() . ' members' : null),
                            TextEntry::make("{$relationship}.visibility")
                                ->hiddenLabel()
                                ->badge()
                                ->visible(fn(?Model $record) => $record?->{$relationship}?->visibility !== 'public'),
                        ]),
                ])->verticallyAlignCenter(),
            ]);
    }

    /**
     * Create a compact band reference (for inline use)
     */
    public static function makeCompact(string $relationship = 'band'): Flex
    {
        return Flex::make([
            ImageEntry::make("{$relationship}.avatar")
                ->hiddenLabel()
                ->circular()
                ->size(40)
                ->grow(false)
                ->defaultImageUrl(fn(?Model $record) => 'https://ui-avatars.com/api/?name=' . urlencode($record?->{$relationship}?->name ?? 'Unknown')),
            Grid::make(1)
                ->gap(0)
                ->schema([
                    TextEntry::make("{$relationship}.name")
                        ->hiddenLabel()
                        ->weight('bold'),
                    TextEntry::make("{$relationship}.genres")
                        ->hiddenLabel()
                        ->badge()
                        ->color('gray')
                        ->size('small')
                        ->limit(2),
                ]),
        ])->verticallyAlignCenter();
    }
}