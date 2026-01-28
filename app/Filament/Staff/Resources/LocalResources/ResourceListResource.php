<?php

namespace App\Filament\Staff\Resources\LocalResources;

use App\Filament\Staff\Resources\LocalResources\Pages\CreateResourceList;
use App\Filament\Staff\Resources\LocalResources\Pages\EditResourceList;
use App\Filament\Staff\Resources\LocalResources\Pages\ListResourceLists;
use App\Filament\Staff\Resources\LocalResources\RelationManagers\ResourcesRelationManager;
use App\Filament\Staff\Resources\LocalResources\Schemas\ResourceListForm;
use App\Filament\Staff\Resources\LocalResources\Tables\ResourceListsTable;
use App\Models\ResourceList;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ResourceListResource extends Resource
{
    protected static ?string $model = ResourceList::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-list';

    protected static UnitEnum|string|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Local Resources';

    protected static ?string $modelLabel = 'Resource List';

    protected static ?string $pluralModelLabel = 'Resource Lists';

    public static function form(Schema $schema): Schema
    {
        return ResourceListForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResourceListsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ResourcesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResourceLists::route('/'),
            'create' => CreateResourceList::route('/create'),
            'edit' => EditResourceList::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
