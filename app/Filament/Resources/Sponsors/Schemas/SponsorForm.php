<?php

namespace App\Filament\Resources\Sponsors\Schemas;

use App\Models\Sponsor;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SponsorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),

                                Select::make('tier')
                                    ->options(Sponsor::getTiers())
                                    ->required()
                                    ->reactive()
                                    ->helperText(fn ($state) => $state ? 'Benefits: ' . self::getBenefitsPreview($state) : null),

                                Select::make('type')
                                    ->options([
                                        Sponsor::TYPE_CASH => 'Cash Sponsorship',
                                        Sponsor::TYPE_IN_KIND => 'In-Kind Partnership',
                                    ])
                                    ->required()
                                    ->default(Sponsor::TYPE_CASH),

                                DatePicker::make('started_at')
                                    ->label('Start Date')
                                    ->helperText('When this sponsorship began'),
                            ]),

                        MarkdownEditor::make('description')
                            ->label('Description')
                            ->helperText('Brief description of the sponsor (displayed on sponsors page)')
                            ->columnSpanFull(),

                        TextInput::make('website_url')
                            ->url()
                            ->prefix('https://')
                            ->maxLength(255)
                            ->helperText('Sponsor\'s website URL (will be linked from logo)'),
                    ]),

                Section::make('Display Settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('display_order')
                                    ->numeric()
                                    ->default(0)
                                    ->helperText('Lower numbers display first'),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Only active sponsors are displayed publicly'),
                            ]),
                    ]),

                Section::make('Logo')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('logo')
                            ->collection('logo')
                            ->image()
                            ->imageEditor()
                            ->maxSize(2048)
                            ->helperText('Upload sponsor logo (PNG, JPG, SVG, or WebP). Transparent backgrounds recommended.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected static function getBenefitsPreview(string $tier): string
    {
        $memberships = match($tier) {
            Sponsor::TIER_HARMONY => '5',
            Sponsor::TIER_MELODY => '10',
            Sponsor::TIER_RHYTHM => '20',
            Sponsor::TIER_CRESCENDO => '25',
            Sponsor::TIER_FUNDRAISING => '5',
            Sponsor::TIER_IN_KIND => '10',
            default => '0',
        };

        $benefits = ["Website logo", "Newsletter listing", "{$memberships} sponsored memberships"];

        if (in_array($tier, [Sponsor::TIER_MELODY, Sponsor::TIER_RHYTHM, Sponsor::TIER_CRESCENDO])) {
            $benefits[] = 'Event logo display';
        }

        if (in_array($tier, [Sponsor::TIER_RHYTHM, Sponsor::TIER_CRESCENDO])) {
            $benefits[] = 'Quarterly newsletter feature';
        }

        if ($tier === Sponsor::TIER_CRESCENDO) {
            $benefits[] = 'Rehearsal space signage';
            $benefits[] = '50% event production discount';
        }

        return implode(', ', $benefits);
    }
}
