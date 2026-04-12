<?php

namespace App\Filament\Staff\Resources\Base\Cards;

use App\Filament\Staff\Resources\Users\UserResource;
use App\Models\User;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;

class UserCard
{
    /**
     * Create a standardized user card section
     */
    public static function make(string $relationship = 'user', ?string $label = null): Section
    {
        return Section::make($label ?? 'User')
            ->icon('tabler-user')
            ->compact()
            ->headerActions([
                \Filament\Actions\Action::make('view_user')
                    ->hiddenLabel()
                    ->icon('tabler-arrow-right')
                    ->iconButton()
                    ->color('gray')
                    ->url(fn(?Model $record) => $record?->{$relationship} ? UserResource::getUrl('edit', ['record' => $record->{$relationship}]) : null)
                    ->openUrlInNewTab(),
            ])
            ->schema([
                Flex::make([
                    ImageEntry::make("{$relationship}.profile.avatar")
                        ->hiddenLabel()
                        ->circular()
                        ->imageSize(60)
                        ->grow(false)
                        ->defaultImageUrl(fn(?Model $record) => 'https://ui-avatars.com/api/?name=' . urlencode($record?->{$relationship}?->name ?? 'Unknown')),
                    Grid::make(1)
                        ->schema([
                            TextEntry::make("{$relationship}.name")
                                ->hiddenLabel()
                                ->weight('bold')
                                ->size('large'),
                            TextEntry::make("{$relationship}.email")
                                ->hiddenLabel()
                                ->icon('tabler-mail')
                                ->iconColor('gray')
                                ->copyable(),
                            TextEntry::make("{$relationship}.phone")
                                ->hiddenLabel()
                                ->icon('tabler-phone')
                                ->iconColor('gray')
                                ->copyable()
                                ->visible(fn(?Model $record) => $record?->{$relationship}?->phone),
                            TextEntry::make("{$relationship}.roles.name")
                                ->hiddenLabel()
                                ->badge()
                                ->color('primary')
                                ->separator(' '),
                        ]),
                ])->verticallyAlignCenter(),
            ]);
    }

    /**
     * Create a compact user reference (for inline use)
     */
    public static function makeCompact(string $relationship = 'user'): Flex
    {
        return Flex::make([
            ImageEntry::make("{$relationship}.profile.avatar")
                ->hiddenLabel()
                ->circular()
                ->imageSize(40)
                ->grow(false)
                ->defaultImageUrl(fn(?Model $record) => 'https://ui-avatars.com/api/?name=' . urlencode($record?->{$relationship}?->name ?? 'Unknown')),
            Grid::make(1)
                ->gap(0)
                ->schema([
                    TextEntry::make("{$relationship}.name")
                        ->hiddenLabel()
                        ->weight('bold'),
                    TextEntry::make("{$relationship}.email")
                        ->hiddenLabel()
                        ->color('gray')
                        ->size('small'),
                    TextEntry::make("{$relationship}.phone")
                        ->hiddenLabel()
                        ->color('gray')
                        ->size('small')
                        ->visible(fn(?Model $record) => $record?->{$relationship}?->phone),
                ]),
        ])->verticallyAlignCenter();
    }
}
