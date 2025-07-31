<?php

namespace App\Filament\Resources\MemberProfiles\Tables;

use App\Models\User;
use App\Settings\MemberDirectorySettings;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MemberProfilesTable
{

    public static function avatarColumn()
    {
        return  ImageColumn::make('avatar_thumb_url')
            ->label('')
            ->circular()
            ->imageSize(64)
            ->grow(false)
            ->defaultImageUrl(function ($record) {
                $name = $record->user?->name ?? 'Member';
                return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&color=7F9CF5&background=EBF4FF&size=160';
            });
    }

    public static function nameColumn()
    {
        return TextColumn::make('user.name')
            ->label('')
            ->weight('bold')
            ->size('lg')
            ->grow(false);
    }

    public static function pronounsColumn()
    {
        return TextColumn::make('user.pronouns')
            ->label('')
            ->color('gray')
            ->size('sm')
            ->prefix('(')
            ->suffix(')')
            ->placeholder('');
    }

    public static function hometownColumn()
    {
        return TextColumn::make('hometown')
            ->grow(false)
            ->label('')
            ->icon('heroicon-s-map-pin')
            ->color('gray')
            ->size('sm');
    }

    public static function bioColumn()
    {
        return TextColumn::make('bio')
            ->state(fn($record) => strip_tags($record->bio))
            ->label('')
            ->lineClamp(2)
            ->color('gray');
    }

    public static function skillsColumn()
    {
        return SpatieTagsColumn::make('skills')
            ->type('skill')
            ->label('')
            ->limit(15)
            ->limitList(3)
            ->badge()
            ->color('gray');
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->searchable(['user.name'])
            ->contentGrid([
                'lg' => 2,
            ])
            ->columns([
                Stack::make([
                    Split::make([
                        static::avatarColumn()
                            ->imageSize(64)
                            ->alignEnd(),
                        Stack::make([
                            Split::make([
                                static::nameColumn(),
                                static::pronounsColumn(),
                            ]),
                            static::hometownColumn(),
                        ])->space(2)
                    ]),
                    static::skillsColumn()->alignCenter(),
                    static::bioColumn()
                ])
                    ->space(3)
                    ->alignCenter()
                    ->hiddenFrom('sm'),

                Stack::make([
                    Split::make([
                        // Avatar section
                        static::avatarColumn(),
                        Stack::make([
                            Split::make([
                                static::nameColumn(),
                                static::pronounsColumn(),
                                static::hometownColumn()
                                    ->iconPosition(IconPosition::After),
                            ]),
                            static::skillsColumn(),
                        ])->space(2)
                    ]),
                    static::bioColumn(),
                ])
                    ->visibleFrom('sm')
                    ->space(2)

            ])

            ->filters([
                SelectFilter::make('visibility')
                    ->visible(fn($record) => User::me()?->can('view private member profiles'))
                    ->options([
                        'public' => 'Public',
                        'members' => 'Members Only',
                        'private' => 'Private',
                    ]),

                SelectFilter::make('directory_flags')
                    ->label('Directory Flags')
                    ->options(function () {
                        $settings = app(MemberDirectorySettings::class);
                        return $settings->getAvailableFlags();
                    })
                    ->query(function ($query, array $data) {
                        if (!empty($data['value'])) {
                            return $query->withFlag($data['value']);
                        }
                        return $query;
                    }),

                SelectFilter::make('skills')
                    ->label('Skills')
                    ->multiple()
                    ->searchable()
                    ->options(function () {
                        return \Spatie\Tags\Tag::where('type', 'skill')
                            ->pluck('name', 'name')
                            ->toArray();
                    })
                    ->query(function ($query, array $data) {
                        if (!empty($data['values'])) {
                            return $query->withAnyTags($data['values'], 'skill');
                        }
                        return $query;
                    }),

                SelectFilter::make('genres')
                    ->label('Genres')
                    ->multiple()
                    ->searchable()
                    ->options(function () {
                        return \Spatie\Tags\Tag::where('type', 'genre')
                            ->pluck('name', 'name')
                            ->toArray();
                    })
                    ->query(function ($query, array $data) {
                        if (!empty($data['values'])) {
                            return $query->withAnyTags($data['values'], 'genre');
                        }
                        return $query;
                    }),

                SelectFilter::make('influences')
                    ->label('Influences')
                    ->multiple()
                    ->searchable()
                    ->options(function () {
                        return \Spatie\Tags\Tag::where('type', 'influence')
                            ->pluck('name', 'name')
                            ->toArray();
                    })
                    ->query(function ($query, array $data) {
                        if (!empty($data['values'])) {
                            return $query->withAnyTags($data['values'], 'influence');
                        }
                        return $query;
                    }),

            ], layout: FiltersLayout::BelowContent)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->iconButton()
                    ->requiresConfirmation()
                    ->successNotificationTitle('Member profile deleted successfully'),
            ])
            ->paginated([10, 25, 50]);
    }
}
