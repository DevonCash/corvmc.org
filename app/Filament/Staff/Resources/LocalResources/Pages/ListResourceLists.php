<?php

namespace App\Filament\Staff\Resources\LocalResources\Pages;

use App\Filament\Staff\Resources\LocalResources\ResourceListResource;
use App\Models\ResourceList;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;

class ListResourceLists extends ListRecords
{
    protected static string $resource = ResourceListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function editCategoryAction(): Action
    {
        return Action::make('editCategory')
            ->label('Edit Category')
            ->modalHeading(fn (array $arguments) => 'Edit Category: ' . ResourceList::find($arguments['id'])?->name)
            ->fillForm(fn (array $arguments) => ResourceList::find($arguments['id'])?->toArray() ?? [])
            ->form([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('display_order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lower numbers display first'),
                ]),

                MarkdownEditor::make('description')
                    ->label('Description'),

                DateTimePicker::make('published_at')
                    ->label('Publish Date')
                    ->helperText('Leave empty to save as draft'),
            ])
            ->action(function (array $data, array $arguments): void {
                ResourceList::find($arguments['id'])?->update($data);
            });
    }
}
