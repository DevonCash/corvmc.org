<?php

namespace App\Filament\Staff\Resources\LocalResources\Tables;

use App\Models\LocalResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class ResourceListsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('website')
                    ->url(fn ($record) => $record->website)
                    ->openUrlInNewTab()
                    ->limit(30)
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->published_at?->isPast() ? 'Published' : ($record->published_at ? 'Scheduled' : 'Draft'))
                    ->color(fn (string $state) => match ($state) {
                        'Published' => 'success',
                        'Scheduled' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultGroup(
                Group::make('resourceList.name')
                    ->label('Category')
                    ->getTitleFromRecordUsing(fn ($record) => $record->resourceList?->name ?? 'Uncategorized')
                    ->getDescriptionFromRecordUsing(function ($record) {
                        $resourceList = $record->resourceList;

                        if (! $resourceList) {
                            return null;
                        }

                        return new HtmlString(Blade::render(
                            '<style>.fi-ta-group-header > div:first-child { flex: 1; display: flex; align-items: center; } .fi-ta-group-header .fi-ta-group-description { margin-left: auto; }</style><x-filament::button wire:click.stop="mountAction(\'editCategory\', { id: {{ $id }} })" size="xs" color="gray" icon="tabler-pencil">Edit</x-filament::button>',
                            ['id' => $resourceList->id],
                        ));
                    })
                    ->titlePrefixedWithLabel(false)
                    ->orderQueryUsing(fn ($query, string $direction) => $query
                        ->leftJoin('resource_lists', 'resource_lists.id', '=', 'local_resources.resource_list_id')
                        ->orderBy('resource_lists.display_order', $direction)
                        ->select('local_resources.*')),
            )
            ->filters([
                SelectFilter::make('published')
                    ->label('Status')
                    ->options([
                        'published' => 'Published',
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                    ])
                    ->query(function ($query, array $data) {
                        return match ($data['value']) {
                            'published' => $query->whereNotNull('published_at')->where('published_at', '<=', now()),
                            'draft' => $query->whereNull('published_at'),
                            'scheduled' => $query->whereNotNull('published_at')->where('published_at', '>', now()),
                            default => $query,
                        };
                    }),

                TrashedFilter::make(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                Action::make('moveUp')
                    ->icon('tabler-arrow-up')
                    ->iconButton()
                    ->color('gray')
                    ->disabled(function (LocalResource $record): bool {
                        $first = LocalResource::query()
                            ->where('resource_list_id', $record->resource_list_id)
                            ->orderBy('sort_order')
                            ->orderBy('name')
                            ->first();

                        return $first?->id === $record->id;
                    })
                    ->action(function (LocalResource $record): void {
                        $siblings = LocalResource::query()
                            ->where('resource_list_id', $record->resource_list_id)
                            ->orderBy('sort_order')
                            ->orderBy('name')
                            ->get();

                        $index = $siblings->search(fn ($r) => $r->id === $record->id);

                        if ($index > 0) {
                            $siblings->splice($index, 1);
                            $siblings->splice($index - 1, 0, [$record]);
                            $siblings->values()->each(fn ($r, $i) => $r->update(['sort_order' => $i]));
                        }
                    }),
                Action::make('moveDown')
                    ->icon('tabler-arrow-down')
                    ->iconButton()
                    ->color('gray')
                    ->disabled(function (LocalResource $record): bool {
                        $last = LocalResource::query()
                            ->where('resource_list_id', $record->resource_list_id)
                            ->orderByDesc('sort_order')
                            ->orderByDesc('name')
                            ->first();

                        return $last?->id === $record->id;
                    })
                    ->action(function (LocalResource $record): void {
                        $siblings = LocalResource::query()
                            ->where('resource_list_id', $record->resource_list_id)
                            ->orderBy('sort_order')
                            ->orderBy('name')
                            ->get();

                        $index = $siblings->search(fn ($r) => $r->id === $record->id);

                        if ($index < $siblings->count() - 1) {
                            $siblings->splice($index, 1);
                            $siblings->splice($index + 1, 0, [$record]);
                            $siblings->values()->each(fn ($r, $i) => $r->update(['sort_order' => $i]));
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
