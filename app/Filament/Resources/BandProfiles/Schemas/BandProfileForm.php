<?php

namespace App\Filament\Resources\BandProfiles\Schemas;

use App\Models\BandProfile;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BandProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->components([
                Grid::make(3)
                    ->columnSpan(3)
                    ->schema([
                        Group::make([
                            TextInput::make('name')
                                ->label('Band Name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Enter your band name')
                                ->columnSpan(2),

                            TextInput::make('hometown')
                                ->label('Location')
                                ->placeholder('City, State/Country')
                                ->maxLength(255)
                                ->columnSpan(1),
                        ])->columns(3)->columnSpanFull(),



                        RichEditor::make('bio')
                            ->label('Biography')
                            ->placeholder('Tell us about your band, your sound, history, and what makes you unique...')
                            ->columnSpanFull()
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike', 'link'],
                                ['h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd'],
                                ['blockquote', 'bulletList', 'orderedList'],
                                ['undo', 'redo'],
                            ]),
                        SpatieTagsInput::make('genres')
                            ->type('genre')
                            ->label('Musical Genres')
                            ->columnSpanFull()
                            ->placeholder('Rock, Pop, Jazz, etc.'),

                        SpatieTagsInput::make('influences')
                            ->type('influence')
                            ->label('Musical Influences')
                            ->columnSpanFull()
                            ->placeholder('Artists or bands that influence your sound'),

                        Fieldset::make('Contact Information')
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('contact.email')
                                    ->label('Email')
                                    ->email()
                                    ->placeholder('band@example.com'),

                                TextInput::make('contact.phone')
                                    ->label('Phone')
                                    ->tel()
                                    ->placeholder('(555) 123-4567'),
                            ])
                            ->columns(2),

                        Fieldset::make('Links')
                            ->columnSpanFull()
                            ->schema([
                                Repeater::make('links')
                                    ->hiddenLabel()
                                    ->label('Links')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Platform/Name')
                                            ->required()
                                            ->maxLength(100)
                                            ->placeholder('e.g., Spotify, Instagram, Website'),

                                        TextInput::make('url')
                                            ->label('URL')
                                            ->required()
                                            ->url()
                                            ->maxLength(500)
                                            ->placeholder('https://...'),
                                    ])
                                    ->table([
                                        TableColumn::make('Platform/Name')
                                            ->alignLeft()
                                            ->width('30%'),
                                        TableColumn::make('URL')
                                            ->alignLeft()
                                            ->width('70%'),
                                    ])
                                    ->columnSpanFull()
                                    ->defaultItems(0),
                            ]),
                    ])
                    ->columns(2),

                Grid::make(1)
                    ->columnSpan(1)
                    ->schema([
                        Fieldset::make('Band Photo')
                            ->columns(1)
                            ->schema([
                                FileUpload::make('avatar')
                                    ->hiddenLabel()
                                    ->image()
                                    ->imageEditor()
                                    ->imageCropAspectRatio('1:1')
                                    ->imageResizeTargetWidth('400')
                                    ->imageResizeTargetHeight('400')
                                    ->directory('band-avatars')
                                    ->visibility('public')
                                    ->alignCenter(),
                            ])
                            ->columnSpanFull(),

                        Select::make('visibility')
                            ->label('Profile Visibility')
                            ->required()
                            ->default('private')
                            ->live()
                            ->options([
                                'private' => 'Private',
                                'members' => 'Members Only',
                                'public' => 'Public',
                            ])
                            ->helperText(fn($state) => match ($state) {
                                'private' => 'Only band members can see your profile',
                                'members' => 'Other CMC members can view your profile',
                                'public' => 'Your profile is visible to the public',
                                default => 'Choose who can see your band profile',
                            })
                            ->selectablePlaceholder(false),

                        Select::make('contact.visibility')
                            ->label('Contact Visibility')
                            ->default('members')
                            ->live()
                            ->options([
                                'private' => 'Private',
                                'members' => 'Members Only',
                                'public' => 'Public',
                            ])
                            ->helperText(fn($state) => match ($state) {
                                'private' => 'Only band members can see contact info',
                                'members' => 'Only other members can see contact info',
                                'public' => 'Anyone who can see your profile can see contact info',
                                default => 'Choose who can see your contact information',
                            })
                            ->selectablePlaceholder(false),

                        Select::make('owner_id')
                            ->label('Band Owner')
                            ->options(
                                fn(?BandProfile $record) =>
                                $record
                                    ? $record->members()->select('users.name', 'users.id')->pluck('name', 'id')->toArray()
                                    : [User::me()->id => User::me()->name]
                            )
                            ->searchable()
                            ->default(fn() => User::me()->id)
                            ->disabled(fn(?BandProfile $record) => $record && !User::me()->can('transferOwnership', $record))
                            ->helperText('The primary contact and administrator for this band'),
                    ]),
            ]);
    }
}
