<?php

namespace App\Filament\Resources\Revisions;

use App\Filament\Resources\Revisions\Pages\ListRevisions;
// use App\Filament\Resources\Revisions\Pages\ViewRevision;
use App\Filament\Resources\Revisions\Schemas\RevisionForm;
use App\Filament\Resources\Revisions\Tables\RevisionsTable;
use CorvMC\Moderation\Models\Revision;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class RevisionResource extends Resource
{
    protected static ?string $model = Revision::class;

    protected static string|BackedEnum|null $navigationIcon = 'tabler-clipboard-check';

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
        return Revision::pending()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $pendingCount = Revision::pending()->count();

        if ($pendingCount > 10) {
            return 'danger';
        } elseif ($pendingCount > 5) {
            return 'warning';
        }

        return 'primary';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return config('revision.show_resource', true);
    }

    public static function canViewAny(): bool
    {
        return config('revision.show_resource', true) && parent::canViewAny();
    }
}
