<?php

namespace App\Filament\Resources\MemberProfiles\Pages;

use App\Filament\Resources\MemberProfiles\MemberProfileResource;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ViewMemberProfile extends Page
{
    use InteractsWithRecord;

    protected static string $resource = MemberProfileResource::class;

    protected string $view = 'filament.resources.member-profiles.pages.view-member-profile';

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return $this->record->user->name;
    }

    public function getSubheading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $location = $this->record->hometown ? " • {$this->record->hometown}" : '';
        return 'Member since ' . $this->record->created_at->format('F Y') . $location;
    }

    public function getBreadCrumbs(): array
    {
        return [
            route('filament.member.resources.directory.index') => 'Member Directory',
            route('filament.member.resources.directory.view', ['record' => $this->record->id]) => $this->record->user->name,
        ];
    }

    public function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn () => 
                    auth()->user()->can('update', $this->record) || 
                    $this->record->user_id === auth()->id()
                ),
        ];
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        
        // Check if user can view this profile
        if (!$this->record->isVisible(auth()->user())) {
            abort(403, 'You do not have permission to view this profile.');
        }
    }

    protected function getViewData(): array
    {
        return [
            'record' => $this->record,
            'canEdit' => auth()->user()->can('update', $this->record) || 
                        $this->record->user_id === auth()->id(),
        ];
    }
}
