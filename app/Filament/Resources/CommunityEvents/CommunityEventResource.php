<?php

namespace App\Filament\Resources\CommunityEvents;

use App\Settings\CommunityCalendarSettings;
use App\Filament\Resources\CommunityEvents\Pages\CreateCommunityEvent;
use App\Filament\Resources\CommunityEvents\Pages\EditCommunityEvent;
use App\Filament\Resources\CommunityEvents\Pages\ListCommunityEvents;
use App\Filament\Resources\CommunityEvents\Pages\ViewCommunityEvent;
use App\Filament\Resources\CommunityEvents\Schemas\CommunityEventForm;
use App\Filament\Resources\CommunityEvents\Tables\CommunityEventsTable;
use App\Models\CommunityEvent;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CommunityEventResource extends Resource
{
    protected static ?string $model = CommunityEvent::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-calendar-event';
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?string $navigationLabel = 'Community Events';
    protected static ?string $modelLabel = 'Community Event';
    protected static ?string $pluralModelLabel = 'Community Events';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return CommunityEventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommunityEventsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunityEvents::route('/'),
            'create' => CreateCommunityEvent::route('/create'),
            'view' => ViewCommunityEvent::route('/{record}'),
            'edit' => EditCommunityEvent::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Get the navigation badge for pending approvals.
     */
    public static function getNavigationBadge(): ?string
    {
        $pendingCount = CommunityEvent::where('status', CommunityEvent::STATUS_PENDING)->count();
        
        return $pendingCount > 0 ? (string) $pendingCount : null;
    }

    /**
     * Get the navigation badge color.
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function shouldRegisterNavigation(): bool
    {
        $communityCalendarSettings = app(CommunityCalendarSettings::class);
        return $communityCalendarSettings->enable_community_calendar;
    }
}