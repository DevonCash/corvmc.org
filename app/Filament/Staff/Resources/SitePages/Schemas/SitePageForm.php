<?php

namespace App\Filament\Staff\Resources\SitePages\Schemas;

use App\Filament\Staff\Resources\SitePages\Blocks\CardBlock;
use App\Filament\Staff\Resources\SitePages\Blocks\SitePageBlockType;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

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

                Textarea::make('content')
                    ->label('Markdown Content')
                    ->helperText('Write page content using markdown with directive syntax. Leave empty to use the block builder below.')
                    ->rows(20)
                    ->columnSpanFull(),

                Builder::make('blocks')
                    ->blocks(SitePageBlockType::blocks())
                    ->extraItemActions([
                        fn (Builder $component): Action => Action::make('colSpan')
                            ->icon('tabler-columns-3')
                            ->color('gray')
                            ->tooltip('Column span')
                            ->fillForm(fn (array $arguments) => [
                                'col_span' => data_get($component->getState(), "{$arguments['item']}.data.col_span", 'auto'),
                            ])
                            ->form([
                                Select::make('col_span')
                                    ->label('Column span')
                                    ->options([
                                        'auto' => 'Auto (1 col)',
                                        'full' => 'Full width',
                                        '2' => '2 columns',
                                        '3' => '3 columns',
                                    ]),
                            ])
                            ->action(function (array $arguments, array $data) use ($component): void {
                                $state = $component->getState();
                                data_set($state, "{$arguments['item']}.data.col_span", $data['col_span']);
                                $component->state($state);
                            })
                            ->visible(fn (array $arguments): bool => data_get(
                                $component->getState(),
                                "{$arguments['item']}.type",
                            ) !== 'section_start'),
                        fn (Builder $component): Action => Action::make('color')
                            ->icon('tabler-palette')
                            ->color('gray')
                            ->tooltip('Color')
                            ->fillForm(fn (array $arguments) => [
                                'color' => data_get($component->getState(), "{$arguments['item']}.data.color", 'base'),
                            ])
                            ->form([
                                Select::make('color')
                                    ->label('Color')
                                    ->options(CardBlock::colorOptions()),
                            ])
                            ->action(function (array $arguments, array $data) use ($component): void {
                                $state = $component->getState();
                                data_set($state, "{$arguments['item']}.data.color", $data['color']);
                                $component->state($state);
                            })
                            ->visible(fn (array $arguments): bool => in_array(
                                data_get($component->getState(), "{$arguments['item']}.type"),
                                ['card', 'stat'],
                            )),
                        fn (Builder $component): Action => Action::make('variant')
                            ->icon('tabler-square-half')
                            ->color('gray')
                            ->tooltip('Variant')
                            ->fillForm(fn (array $arguments) => [
                                'variant' => data_get($component->getState(), "{$arguments['item']}.data.variant", 'solid'),
                            ])
                            ->form([
                                Select::make('variant')
                                    ->label('Variant')
                                    ->options([
                                        'solid' => 'Solid',
                                        'outline' => 'Outline',
                                    ]),
                            ])
                            ->action(function (array $arguments, array $data) use ($component): void {
                                $state = $component->getState();
                                data_set($state, "{$arguments['item']}.data.variant", $data['variant']);
                                $component->state($state);
                            })
                            ->visible(fn (array $arguments): bool => data_get(
                                $component->getState(),
                                "{$arguments['item']}.type",
                            ) === 'button'),
                    ])
                    ->collapsible()
                    ->blockNumbers(false)
                    ->columnSpanFull()
                    ->label('Page Content'),
            ]);
    }
}
