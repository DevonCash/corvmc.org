<?php

namespace App\Filament\Resources\CommunityEvents\Pages;

use App\Filament\Resources\CommunityEvents\CommunityEventResource;
use App\Models\CommunityEvent;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListCommunityEvents extends ListRecords
{
    protected static string $resource = CommunityEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Events'),
            
            'pending' => Tab::make('Pending Approval')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', CommunityEvent::STATUS_PENDING))
                ->badge(CommunityEvent::where('status', CommunityEvent::STATUS_PENDING)->count())
                ->badgeColor('warning'),
            
            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', CommunityEvent::STATUS_APPROVED)),
            
            'upcoming' => Tab::make('Upcoming')
                ->modifyQueryUsing(fn (Builder $query) => $query->approvedUpcoming()),
            
            'local' => Tab::make('Local Events')
                ->modifyQueryUsing(fn (Builder $query) => $query->local()),
            
            'my_events' => Tab::make('My Events')
                ->modifyQueryUsing(fn (Builder $query) => $query->byOrganizer(Auth::user()->id))
                ->visible(fn () => Auth::user()->can('create community events')),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->with(['organizer', 'reports']);
    }
}