<?php

namespace App\Filament\Resources\Bands\Schemas;

use CorvMC\Moderation\Enums\Visibility;
use App\Filament\Components\EmbedControl;
use CorvMC\Bands\Models\Band;
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
use Illuminate\Support\Facades\Auth;

class BandForm
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
                                    ->rules(['phone:US,AUTO'])
                                    ->validationMessages([
                                        'phone' => 'Please enter a valid phone number',
                                    ])
                                    ->placeholder('(555) 123-4567'),
                            ])
                            ->columns(2),

                        Fieldset::make('Embeds & Media')
                            ->columnSpanFull()
                            ->schema([
                                EmbedControl::make(),
                            ]),

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
                        SpatieMediaLibraryFileUpload::make('avatar')
                            ->label('Band Photo')
                            ->disk('r2')
                            ->image()
                            ->imageEditor()
                            ->imageCropAspectRatio('1:1')
                            ->imageResizeTargetWidth(800)
                            ->imageResizeTargetHeight(800)
                            ->directory('band-avatars')
                            ->collection('avatar')
                            ->visibility('public')
                            ->alignCenter()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                            ->maxSize(3072)
                            ->imageResizeMode('cover'),

                        Select::make('visibility')
                            ->label('Profile Visibility')
                            ->required()
                            ->default(Visibility::Private)
                            ->live()
                            ->options(Visibility::class)
                            ->helperText(fn ($state) => $state ? $state->getDescription() : 'Choose who can see your band profile')
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
                            ->helperText(fn ($state) => match ($state) {
                                'private' => 'Only band members can see contact info',
                                'members' => 'Only other members can see contact info',
                                'public' => 'Anyone who can see your profile can see contact info',
                                default => 'Choose who can see your contact information',
                            })
                            ->selectablePlaceholder(false),

                        Select::make('owner_id')
                            ->label('Band Owner')
                            ->options(function (?Band $record) {
                                if (! $record) {
                                    return [Auth::user()->id => Auth::user()->name];
                                }

                                return $record->members->pluck('name', 'id')->toArray();
                            })
                            ->preload()
                            ->default(fn () => Auth::user()->id)
                            ->disabled(fn (?Band $record) => $record && ! Auth::user()->can('transferOwnership', $record))
                            ->helperText('The primary contact and administrator for this band'),
                    ]),
            ]);
    }
}
