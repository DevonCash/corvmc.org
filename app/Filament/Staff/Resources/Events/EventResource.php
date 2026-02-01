<?php

namespace App\Filament\Staff\Resources\Events;

use App\Filament\Staff\Resources\Events\Pages\EditEvent;
use App\Filament\Staff\Resources\Events\Pages\ListEvents;
use App\Filament\Staff\Resources\Events\RelationManagers\PerformersRelationManager;
use App\Filament\Staff\Resources\Events\RelationManagers\TicketOrdersRelationManager;
use App\Filament\Staff\Resources\Events\Schemas\EventForm;
use App\Filament\Staff\Resources\Events\Tables\EventsTable;
use CorvMC\Events\Models\Event;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-music';

    protected static ?string $recordTitleAttribute = 'title';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    public static function form(Schema $schema): Schema
    {
        return EventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PerformersRelationManager::class,
            'ticketOrders' => TicketOrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'edit' => EditEvent::route('/{record}/edit'),
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
