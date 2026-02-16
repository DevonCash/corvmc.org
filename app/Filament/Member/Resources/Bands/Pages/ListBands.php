<?php

namespace App\Filament\Member\Resources\Bands\Pages;

use CorvMC\Membership\Actions\Bands\CreateBand;
use App\Filament\Member\Resources\Bands\BandResource;
use App\Filament\Member\Resources\Bands\Widgets\PendingBandInvitationsWidget;
use CorvMC\Bands\Models\Band;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ListBands extends Page
{
    protected static ?string $breadcrumb = '';

    protected static string $resource = BandResource::class;

    protected string $view = 'bands::filament.pages.list-bands';

    protected static ?string $title = 'Bands';

    public $filters = [
        'genres' => [],
        'hometown' => null,
    ];

    public $search = '';

    public $filtersCollapsed = true;

    protected $queryString = [
        'filtersCollapsed' => ['except' => true],
        'search' => ['except' => ''],
    ];

    public function getSubNavigation(): array
    {
        if (filled($cluster = static::getCluster())) {
            return $this->generateNavigationItems($cluster::getClusteredComponents());
        }

        return [];
    }

    public function hasResourceBreadcrumbs(): bool
    {
        return false;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PendingBandInvitationsWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateBand::filamentAction(),
        ];
    }

    public function getBands()
    {
        $query = Band::with(['tags', 'activeMembers', 'media'])
            ->visibleTo(User::me());

        // Apply search
        if (! empty($this->search)) {
            $query->where(function (Builder $query) {
                $searchTerm = '%'.$this->search.'%';
                $query->where('name', 'like', $searchTerm)
                    ->orWhere('hometown', 'like', $searchTerm)
                    ->orWhere('bio', 'like', $searchTerm);
            });
        }

        // Apply filters
        if (! empty($this->filters['genres'])) {
            $query->withAnyTags($this->filters['genres'], 'genre');
        }

        if (! empty($this->filters['hometown'])) {
            $query->where('hometown', $this->filters['hometown']);
        }

        return $query->orderBy('name')->paginate(12);
    }

    public function clearFilters()
    {
        $this->filters = [
            'genres' => [],
            'hometown' => null,
        ];
        $this->search = '';
    }

    protected function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
            ->columns(2)
            ->live()
            ->schema([
                Select::make('genres')
                    ->label('Genres')
                    ->multiple()
                    ->searchable()
                    ->live()
                    ->options(function () {
                        return \Spatie\Tags\Tag::where('type', 'genre')
                            ->pluck('name', 'name')
                            ->toArray();
                    }),

                Select::make('hometown')
                    ->label('Location')
                    ->searchable()
                    ->live()
                    ->options(function () {
                        return Band::whereNotNull('hometown')
                            ->distinct()
                            ->pluck('hometown', 'hometown')
                            ->sort()
                            ->toArray();
                    }),
            ]);
    }
}
