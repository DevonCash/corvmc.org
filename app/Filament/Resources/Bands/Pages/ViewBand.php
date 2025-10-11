<?php

namespace App\Filament\Resources\Bands\Pages;

use App\Actions\Bands\InviteMember;
use App\Filament\Actions\ReportContentAction;
use App\Filament\Resources\Bands\BandResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class ViewBand extends Page
{
    use InteractsWithRecord;

    protected static string $resource = BandResource::class;

    protected string $view = 'filament.resources.band-profiles.pages.view-band-profile';

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return $this->record->name;
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $genres = $this->record->tagsWithType('genre')->pluck('name');
        $location = $this->record->hometown ? " • {$this->record->hometown}" : '';

        if ($genres->count() > 0) {
            $genreText = $genres->take(3)->join(', ');

            return $genreText.$location;
        }

        return 'Band'.$location;
    }

    public function getBreadCrumbs(): array
    {
        return [
            route('filament.member.resources.bands.index') => 'Band Profiles',
            route('filament.member.resources.bands.view', ['record' => $this->record]) => $this->record->name,
        ];
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('invite_member')
                ->label('Invite Band Member')
                ->icon('heroicon-o-user-plus')
                ->modalHeading('Invite Band Member')
                ->modalWidth('md')
                ->visible(fn () => Auth::user()->can('invite', $this->record))
                ->form([
                    Select::make('user_id')
                        ->label('User')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) =>
                            User::where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->limit(50)
                                ->pluck('name', 'id')
                        )
                        ->getOptionLabelUsing(fn ($value): ?string => User::find($value)?->name)
                        ->placeholder('Search for a user...'),

                    TextInput::make('position')
                        ->label('Position/Instrument')
                        ->placeholder('e.g., Guitarist, Vocalist, Drummer')
                        ->maxLength(255),

                    Select::make('role')
                        ->label('Role')
                        ->options([
                            'member' => 'Member',
                            'admin' => 'Admin',
                        ])
                        ->default('member')
                        ->required()
                        ->helperText('Admins can manage band settings and invite other members'),
                ])
                ->action(function (array $data) {
                    InviteMember::run(
                        band: $this->record,
                        user: User::find($data['user_id']),
                        role: $data['role'],
                        position: $data['position'] ?? null
                    );

                    $this->notify('success', 'Band member invitation sent!');
                }),
            EditAction::make()
                ->visible(fn () => Auth::user()->can('update', $this->record)),
            ReportContentAction::make()
                ->visible(fn () => Auth::user()->id !== $this->record->owner_id), // Don't show report button to owner
        ];
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        // Check if user can view this profile
        if (! Auth::user()->can('view', $this->record)) {
            abort(403, 'You do not have permission to view this band profile.');
        }
    }

    public function getHeader(): ?View
    {
        return null;
    }

    protected function getViewData(): array
    {
        return [
            'record' => $this->record,
        ];
    }
}
