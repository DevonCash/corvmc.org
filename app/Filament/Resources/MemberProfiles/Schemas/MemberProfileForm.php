<?php

namespace App\Filament\Resources\MemberProfiles\Schemas;

use App\Models\MemberProfile;
use App\Settings\MemberDirectorySettings;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;

class MemberProfileForm
{
    public static function configure($schema)
    {
        return $schema
            ->columns(4)
            ->components([
                Grid::make(3)
                    ->columnSpan(3)
                    ->schema([
                        Group::make([
                            Group::make([
                                TextInput::make('name')
                                    ->label('Name')
                                    ->required()
                                    ->columnSpan(2)
                                    ->maxLength(255)
                                    ->placeholder('Enter your name'),
                                TextInput::make('pronouns')
                                    ->label('Pronouns')
                                    ->maxLength(50)
                                    ->placeholder('e.g. he/him, she/her, they/them'),
                            ])->columns(3)
                                ->columnSpan(3)
                                ->relationship('user'),
                            TextInput::make('hometown')
                                ->label('Hometown')
                                ->columnSpan(1)
                                ->datalist(fn() => MemberProfile::withoutGlobalScope(\App\Models\Scopes\MemberVisibilityScope::class)->distinct()->pluck('hometown')->concat(['Corvallis', 'Albany', 'Philomath', 'Monroe', 'Lebanon', 'Sweet Home', 'Eugene', 'Springfield', 'Portland', 'Salem'])),
                        ])->columns(4)->columnSpanFull(),
                        RichEditor::make('bio')
                            ->label('Bio')
                            ->placeholder('Tell us about yourself')
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike', 'link'],
                                ['h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd'],
                                ['blockquote', 'bulletList', 'orderedList'],
                                ['table'], // The `customBlocks` and `mergeTags` tools are also added here if those features are used.
                                ['undo', 'redo'],
                            ])
                            ->columnSpanFull(),
                        Fieldset::make('Contact Information')
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('contact.email')
                                    ->label('Email')
                                    ->email()
                                    ->placeholder('business@example.com'),

                                TextInput::make('contact.phone')
                                    ->label('Phone')
                                    ->tel()
                                    ->placeholder('(555) 123-4567'),
                            ])
                            ->columns(2),
                        Fieldset::make('Skills & Interests')
                            ->schema([
                                SpatieTagsInput::make('skills')
                                    ->type('skill')
                                    ->label('Skills')
                                    ->columnSpanFull()
                                    ->placeholder('Vocalist, Guitarist, Producer, etc.'),

                                SpatieTagsInput::make('genres')
                                    ->type('genre')
                                    ->label('Genres')
                                    ->columnSpanFull()

                                    ->placeholder('Rock, Pop, Jazz, etc.'),

                                SpatieTagsInput::make('influences')
                                    ->type('influence')
                                    ->label('Influences')
                                    ->columnSpanFull()
                                    ->placeholder('Artists or bands that inspire you'),
                            ])
                            ->columnSpanFull(),

                        Fieldset::make('Links')
                            ->columnSpanFull()
                            ->schema([
                                Repeater::make('links')
                                    ->hiddenLabel()
                                    ->label('Links')
                                    ->columns(3)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Platform/Link Name')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('e.g. Instagram, Bandcamp, Spotify'),
                                        TextInput::make('url')
                                            ->label('URL')
                                            ->required()
                                            ->url()
                                            ->maxLength(255)
                                            ->placeholder('https://...'),
                                    ])
                                    ->table([
                                        TableColumn::make('Name')->alignLeft()
                                            ->width('30%'),
                                        TableColumn::make('URL')->alignLeft(),
                                    ])
                                    ->columnSpanFull()
                                    ->defaultItems(0),
                            ]),

                    ])
                    ->columns(2),
                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                                SpatieMediaLibraryFileUpload::make('avatar')
                                    ->label('Profile Picture')
                                    ->collection('avatar')
                                    ->disk('r2')
                                    ->alignCenter()
                                    ->avatar(),

                        Select::make('visibility')
                            ->label('Profile Visibility')
                            ->live()
                            ->options([
                                'private' => 'Private',
                                'members' => 'Members Only',
                                'public' => 'Public',
                            ])
                            ->helperText(fn($state) => match ($state) {
                                'private' => 'Only you can see your profile',
                                'members' => 'Other CMC members can view your profile',
                                'public' => 'Your profile is visible to the public',
                            })
                            ->selectablePlaceholder(false)
                            ->default('private')
                            ->required(),

                        Select::make('contact.visibility')
                            ->label('Contact Visibility')
                            ->live()

                            ->options([
                                'private' => 'Private',
                                'members' => 'Members Only',
                                'public' => 'Public',
                            ])
                            ->helperText(fn($state) => match ($state) {
                                'private' => 'Only CMC staff can see your contact info',
                                'members' => 'Only other members can see your contact info',
                                'public' => 'Anyone who can see your profile can see your contact info',
                                default => $state
                            })
                            ->selectablePlaceholder(false)
                            ->default('private')
                            ->required(),

                        Fieldset::make('Directory Flags')
                            ->columns(2)
                            ->schema([
                                CheckboxList::make('directory_flags')
                                    ->hiddenLabel()
                                    ->options(function () {
                                        $settings = app(MemberDirectorySettings::class);

                                        return $settings->getAvailableFlags();
                                    })
                                    ->descriptions([
                                        'open_to_collaboration' => 'Open to musical collaborations and creative partnerships',
                                        'available_for_hire' => 'Available for paid musical services (sessions, performances, etc.)',
                                        'looking_for_band' => 'Actively seeking to join or form a band',
                                        'music_teacher' => 'Available to teach music lessons',
                                    ])
                                    ->afterStateHydrated(function (CheckboxList $component, $record) {
                                        if (! $record) {
                                            return;
                                        }

                                        $settings = app(MemberDirectorySettings::class);
                                        $activeFlags = [];

                                        foreach ($settings->getAvailableFlags() as $flag => $label) {
                                            if ($record->hasFlag($flag)) {
                                                $activeFlags[] = $flag;
                                            }
                                        }

                                        $component->state($activeFlags);
                                    })
                                    ->dehydrated(false)
                                    ->columns(1)
                                    ->columnSpanFull(),
                            ]),
                    ]),

            ]);
    }
}
