<?php

namespace App\Filament\Staff\Resources\SpaceClosures;

use App\Filament\Staff\Resources\SpaceClosures\Pages\EditSpaceClosure;
use App\Filament\Staff\Resources\SpaceClosures\Pages\ListSpaceClosures;
use App\Filament\Staff\Resources\SpaceClosures\Schemas\SpaceClosureForm;
use App\Filament\Staff\Resources\SpaceClosures\Tables\SpaceClosuresTable;
use CorvMC\SpaceManagement\Models\SpaceClosure;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class SpaceClosureResource extends Resource
{
    protected static ?string $model = SpaceClosure::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-calendar-off';

    public static function getRecordTitle($record): ?string
    {
        return $record->type->getLabel() . ' - ' . $record->starts_at->format('M j, Y');
    }

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return SpaceClosureForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SpaceClosuresTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpaceClosures::route('/'),
            'edit' => EditSpaceClosure::route('/{record}/edit'),
        ];
    }
}
