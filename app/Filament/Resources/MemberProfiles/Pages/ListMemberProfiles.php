<?php

namespace App\Filament\Resources\MemberProfiles\Pages;

use App\Filament\Actions\InviteUserAction;
use App\Filament\Resources\MemberProfiles\MemberProfileResource;
use App\Models\MemberProfile;
use App\Models\User;
use App\Settings\MemberDirectorySettings;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ListMemberProfiles extends Page
{

    protected static ?string $breadcrumb = '';
    public function hasResourceBreadcrumbs(): bool
    {
        return false;
    }
    protected static string $resource = MemberProfileResource::class;

    protected string $view = 'filament.resources.member-profiles.pages.list-member-profiles';

    protected static ?string $title = 'Members';

    public $filters = [
        'skills' => [],
        'genres' => [],
        'influences' => [],
        'flags' => [],
        'visibility' => null,
    ];

    public $search = '';

    public $filtersCollapsed = true;

    protected $queryString = [
        'filtersCollapsed' => ['except' => true],
        'search' => ['except' => ''],
    ];

    protected function getHeaderActions(): array
    {
        return [
            InviteUserAction::make(),
            EditAction::make()
                ->label('Edit My Profile')
                ->record(fn(): MemberProfile => Auth::user()->profile),
        ];
    }

    public function getMembers()
    {
        $query = MemberProfile::with(['user', 'tags'])
            ->whereHas('user', function (Builder $query) {
                $query->where('name', '!=', '');
            });

        // Apply search
        if (!empty($this->search)) {
            $query->where(function (Builder $query) {
                $searchTerm = '%' . $this->search . '%';

                // Search user name
                $query->whereHas('user', function (Builder $subQuery) use ($searchTerm) {
                    $subQuery->where('name', 'like', $searchTerm);
                })
                    // Search bio
                    ->orWhere('bio', 'like', $searchTerm)
                    // Search links JSON field
                    ->orWhere('links', 'like', $searchTerm)
                    // Search contact JSON field
                    ->orWhere('contact', 'like', $searchTerm);
            });
        }

        // Apply filters
        if (!empty($this->filters['flags'])) {
            foreach ($this->filters['flags'] as $flag) {
                $query->withFlag($flag);
            }
        }

        if (!empty($this->filters['skills'])) {
            $query->withAnyTags($this->filters['skills'], 'skill');
        }

        if (!empty($this->filters['genres'])) {
            $query->withAnyTags($this->filters['genres'], 'genre');
        }

        if (!empty($this->filters['influences'])) {
            $query->withAnyTags($this->filters['influences'], 'influence');
        }

        return $query->paginate(12);
    }

    public function clearFilters()
    {
        $this->filters = [
            'skills' => [],
            'genres' => [],
            'influences' => [],
            'flags' => [],
            'visibility' => null,
        ];
        $this->search = '';
    }


    protected function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
            ->columns(3)
            ->live()
            ->schema([
                Select::make('skills')
                    ->label('Skills')
                    ->multiple()
                    ->searchable()
                    ->live()
                    ->options(function () {
                        return \Spatie\Tags\Tag::where('type', 'skill')
                            ->pluck('name', 'name')
                            ->toArray();
                    }),

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

                Select::make('influences')
                    ->label('Influences')
                    ->multiple()
                    ->searchable()
                    ->live()
                    ->options(function () {
                        return \Spatie\Tags\Tag::where('type', 'influence')
                            ->pluck('name', 'name')
                            ->toArray();
                    }),

                CheckboxList::make('flags')
                    ->hiddenLabel()
                    ->live()
                    ->options(function () {
                        $settings = app(MemberDirectorySettings::class);
                        return $settings->getAvailableFlags();
                    })
                    ->columnSpan(fn() => Auth::user()?->can('view private member profiles') ? 2 : 3)
                    ->columns(function () {
                        return Auth::user()?->can('view private member profiles') ? 2 : 4;
                    }),

                Select::make('visibility')
                    ->label('Visibility 🛡️')
                    ->visible(fn() => Auth::user()?->can('view private member profiles'))
                    ->live()
                    ->options([
                        'public' => 'Public',
                        'members' => 'Members Only',
                        'private' => 'Private',
                    ]),
            ]);
    }
}
