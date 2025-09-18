<?php

namespace App\Filament\Resources\Revisions;

use App\Filament\Resources\Revisions\Pages\ListRevisions;
// use App\Filament\Resources\Revisions\Pages\ViewRevision;
use App\Filament\Resources\Revisions\Schemas\RevisionForm;
use App\Filament\Resources\Revisions\Tables\RevisionsTable;
use App\Models\Revision;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RevisionResource extends Resource
{
    protected static ?string $model = Revision::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;
    
    protected static ?string $navigationLabel = 'Revisions';
    
    protected static ?string $modelLabel = 'Revision';
    
    protected static ?string $pluralModelLabel = 'Revisions';
    
    protected static string|\UnitEnum|null $navigationGroup = 'Moderation';
    
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return RevisionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RevisionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRevisions::route('/'),
            // 'view' => ViewRevision::route('/{record}'),
        ];
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::pending()->count() ?: null;
    }
    
    public static function getNavigationBadgeColor(): string|array|null
    {
        $pendingCount = static::getModel()::pending()->count();
        
        if ($pendingCount > 10) {
            return 'danger';
        } elseif ($pendingCount > 5) {
            return 'warning';
        }
        
        return 'primary';
    }
}
