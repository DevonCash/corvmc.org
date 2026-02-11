<?php

namespace App\Filament\Staff\Resources\SitePages\Schemas;

use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class SitePageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),

                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->disabled()
                    ->dehydrated(),

                Builder::make('blocks')
                    ->blocks([
                        self::sectionBlock(),
                    ])
                    ->collapsible()
                    ->blockNumbers(false)
                    ->columnSpanFull()
                    ->label('Page Content'),
            ]);
    }

    private static function sectionBlock(): Block
    {
        return Block::make('section')
            ->label(function (?array $state): string {
                $bg = $state['background_color'] ?? 'none';
                $items = $state['items'] ?? [];

                // Find first header item for label
                $headerText = '';
                foreach ($items as $item) {
                    if (($item['type'] ?? '') === 'header') {
                        $headerText = $item['data']['heading'] ?? '';
                        break;
                    }
                }

                $label = ucfirst($bg);
                if ($headerText) {
                    $label .= ": {$headerText}";
                }

                return $label;
            })
            ->icon('tabler-layout-rows')
            ->schema([
                Grid::make(4)->schema([
                    Select::make('background_color')
                        ->options([
                            'none' => 'None',
                            'success' => 'Success (green)',
                            'primary' => 'Primary (blue)',
                            'info' => 'Info (cyan)',
                            'warning' => 'Warning (amber)',
                            'secondary' => 'Secondary',
                            'accent' => 'Accent',
                        ])
                        ->default('none')
                        ->required(),

                    Select::make('columns')
                        ->options([
                            1 => '1 column',
                            2 => '2 columns',
                            3 => '3 columns',
                            4 => '4 columns',
                        ])
                        ->default(2)
                        ->required(),

                    Toggle::make('full_bleed')
                        ->label('Full bleed (hero)')
                        ->default(false),
                ]),

                Repeater::make('items')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('type')
                                ->options([
                                    'header' => 'Header',
                                    'prose' => 'Prose (Markdown)',
                                    'card' => 'Card',
                                    'detailed_card' => 'Detailed Card',
                                    'card_stack' => 'Card Stack',
                                    'alert' => 'Alert',
                                    'stat' => 'Stat',
                                    'step' => 'Step',
                                    'button' => 'Button',
                                ])
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    // Set sensible col_span defaults
                                    if (in_array($state, ['header', 'button'])) {
                                        $set('data.col_span', 'full');
                                    } else {
                                        $set('data.col_span', 'auto');
                                    }
                                }),

                            Select::make('data.col_span')
                                ->label('Column span')
                                ->options([
                                    'auto' => 'Auto (1 col)',
                                    'full' => 'Full width',
                                    '2' => '2 columns',
                                    '3' => '3 columns',
                                ])
                                ->default('auto'),
                        ]),

                        // Header fields
                        Section::make('Header')
                            ->schema([
                                TextInput::make('data.heading')
                                    ->label('Heading')
                                    ->required(),
                                Textarea::make('data.description')
                                    ->label('Description')
                                    ->rows(2),
                                TextInput::make('data.icon')
                                    ->label('Icon')
                                    ->placeholder('tabler-music'),
                            ])
                            ->visible(fn (callable $get) => $get('type') === 'header')
                            ->compact(),

                        // Prose fields
                        Section::make('Prose')
                            ->schema([
                                MarkdownEditor::make('data.content')
                                    ->label('Content')
                                    ->required(),
                                Grid::make(3)->schema([
                                    TextInput::make('data.alert_icon')
                                        ->label('Alert icon')
                                        ->placeholder('tabler-info-circle'),
                                    TextInput::make('data.alert_text')
                                        ->label('Alert text')
                                        ->columnSpan(2),
                                ]),
                                Select::make('data.alert_style')
                                    ->label('Alert style')
                                    ->options([
                                        'info' => 'Info',
                                        'warning' => 'Warning',
                                        'success' => 'Success',
                                        'error' => 'Error',
                                    ])
                                    ->default('info'),
                            ])
                            ->visible(fn (callable $get) => $get('type') === 'prose')
                            ->compact(),

                        // Card fields
                        Section::make('Card')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('data.icon')
                                        ->label('Icon')
                                        ->placeholder('tabler-music'),
                                    TextInput::make('data.heading')
                                        ->label('Heading')
                                        ->required(),
                                ]),
                                Textarea::make('data.body')
                                    ->label('Body')
                                    ->rows(2),
                                Repeater::make('data.features')
                                    ->label('Features')
                                    ->simple(TextInput::make('text')->required())
                                    ->collapsible()
                                    ->collapsed(),
                                Select::make('data.color')
                                    ->label('Color')
                                    ->options(self::colorOptions())
                                    ->default('base'),
                            ])
                            ->visible(fn (callable $get) => $get('type') === 'card')
                            ->compact(),

                        // Detailed Card fields
                        Section::make('Detailed Card')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('data.name')
                                        ->label('Name')
                                        ->required(),
                                    TextInput::make('data.icon')
                                        ->label('Icon')
                                        ->placeholder('tabler-music'),
                                    TextInput::make('data.icon_color')
                                        ->label('Icon bg color')
                                        ->placeholder('bg-amber-500'),
                                ]),
                                Textarea::make('data.description')
                                    ->label('Description')
                                    ->rows(2),
                                Repeater::make('data.details')
                                    ->label('Details')
                                    ->schema([
                                        TextInput::make('label')->required(),
                                        Textarea::make('value')->required()->rows(2),
                                    ])
                                    ->collapsible()
                                    ->collapsed()
                                    ->columns(2),
                                TextInput::make('data.activities_label')
                                    ->label('Activities label')
                                    ->placeholder('What We Do'),
                                Repeater::make('data.activities')
                                    ->label('Activities')
                                    ->simple(TextInput::make('text')->required())
                                    ->collapsible()
                                    ->collapsed(),
                                TextInput::make('data.tip')
                                    ->label('Tip text'),
                            ])
                            ->visible(fn (callable $get) => $get('type') === 'detailed_card')
                            ->compact(),

                        // Card Stack fields
                        Section::make('Card Stack')
                            ->schema([
                                Repeater::make('data.cards')
                                    ->label('Cards')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('name')->required(),
                                            TextInput::make('badge')
                                                ->placeholder('2nd Saturday • 2:00 PM'),
                                        ]),
                                        Grid::make(2)->schema([
                                            TextInput::make('icon')
                                                ->placeholder('tabler-users'),
                                            TextInput::make('icon_color')
                                                ->placeholder('text-primary'),
                                        ]),
                                        Textarea::make('description')
                                            ->rows(2),
                                    ])
                                    ->collapsible()
                                    ->collapsed()
                                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                            ])
                            ->visible(fn (callable $get) => $get('type') === 'card_stack')
                            ->compact(),

                        // Alert fields
                        Section::make('Alert')
                            ->schema([
                                TextInput::make('data.icon')
                                    ->label('Icon')
                                    ->placeholder('tabler-info-circle'),
                                TextInput::make('data.text')
                                    ->label('Text')
                                    ->required(),
                                Select::make('data.style')
                                    ->label('Style')
                                    ->options([
                                        'info' => 'Info',
                                        'warning' => 'Warning',
                                        'success' => 'Success',
                                        'error' => 'Error',
                                    ])
                                    ->default('info'),
                            ])
                            ->visible(fn (callable $get) => $get('type') === 'alert')
                            ->compact(),

                        // Stat fields
                        Section::make('Stat')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('data.label')
                                        ->label('Label')
                                        ->required(),
                                    TextInput::make('data.value')
                                        ->label('Value')
                                        ->required(),
                                ]),
                                TextInput::make('data.subtitle')
                                    ->label('Subtitle'),
                                Select::make('data.color')
                                    ->label('Color')
                                    ->options(self::colorOptions())
                                    ->default('base'),
                            ])
                            ->visible(fn (callable $get) => $get('type') === 'stat')
                            ->compact(),

                        // Step fields
                        Section::make('Step')
                            ->schema([
                                TextInput::make('data.icon')
                                    ->label('Icon')
                                    ->placeholder('tabler-number-1'),
                                TextInput::make('data.title')
                                    ->label('Title')
                                    ->required(),
                                TextInput::make('data.description')
                                    ->label('Description'),
                            ])
                            ->visible(fn (callable $get) => $get('type') === 'step')
                            ->compact(),

                        // Button fields
                        Section::make('Button')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('data.label')
                                        ->label('Label')
                                        ->required(),
                                    TextInput::make('data.url')
                                        ->label('URL')
                                        ->required()
                                        ->placeholder('/events'),
                                ]),
                                Select::make('data.style')
                                    ->label('Style')
                                    ->options([
                                        'primary' => 'Primary',
                                        'info' => 'Info',
                                        'success' => 'Success',
                                        'warning' => 'Warning',
                                        'outline-primary' => 'Outline Primary',
                                        'outline-secondary' => 'Outline Secondary',
                                        'outline-info' => 'Outline Info',
                                        'outline-success' => 'Outline Success',
                                    ])
                                    ->default('primary'),
                            ])
                            ->visible(fn (callable $get) => $get('type') === 'button')
                            ->compact(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->itemLabel(function (array $state): ?string {
                        $type = $state['type'] ?? '';
                        $data = $state['data'] ?? [];

                        return match ($type) {
                            'header' => 'Header: '.($data['heading'] ?? ''),
                            'prose' => 'Prose',
                            'card' => 'Card: '.($data['heading'] ?? ''),
                            'detailed_card' => 'Detailed Card: '.($data['name'] ?? ''),
                            'card_stack' => 'Card Stack ('.count($data['cards'] ?? []).')',
                            'alert' => 'Alert: '.Str::limit($data['text'] ?? '', 30),
                            'stat' => 'Stat: '.($data['label'] ?? ''),
                            'step' => 'Step: '.($data['title'] ?? ''),
                            'button' => 'Button: '.($data['label'] ?? ''),
                            default => $type,
                        };
                    })
                    ->reorderable()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function colorOptions(): array
    {
        return [
            'base' => 'Base',
            'success' => 'Success (green)',
            'primary' => 'Primary (blue)',
            'info' => 'Info (cyan)',
            'warning' => 'Warning (amber)',
            'secondary' => 'Secondary',
            'accent' => 'Accent',
        ];
    }
}
