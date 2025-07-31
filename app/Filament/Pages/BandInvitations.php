<?php

namespace App\Filament\Pages;

use App\Models\BandProfile;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class BandInvitations extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';
    
    protected string $view = 'filament.pages.band-invitations';
    
    protected static ?string $navigationLabel = 'Band Invitations';
    
    protected static ?int $navigationSort = 25;
    
    public static function getNavigationBadge(): ?string
    {
        $count = BandProfile::whereHas('members', function ($query) {
            $query->where('user_id', auth()->id())
                  ->where('status', 'invited');
        })->count();
        
        return $count > 0 ? (string) $count : null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
    
    public static function shouldRegisterNavigation(): bool
    {
        // Only show in navigation if user has pending invitations
        return self::getNavigationBadge() !== null;
    }

    public function mount(): void
    {
        // Check if user has any pending invitations
        $invitations = $this->getPendingInvitations();
        
        if ($invitations->isEmpty()) {
            $this->redirect(route('filament.member.resources.band-profiles.index'));
            return;
        }
    }

    public function getPendingInvitations(): Collection
    {
        return BandProfile::whereHas('members', function ($query) {
            $query->where('user_id', auth()->id())
                  ->where('status', 'invited');
        })->with(['members' => function ($query) {
            $query->where('user_id', auth()->id())
                  ->where('status', 'invited')
                  ->withPivot('role', 'position', 'invited_at');
        }])->get();
    }

    protected function getActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-m-arrow-path')
                ->action(fn () => $this->redirect(request()->url())),
        ];
    }

    public function acceptInvitation(int $bandId): void
    {
        $band = BandProfile::findOrFail($bandId);
        $user = auth()->user();
        
        if ($band->hasInvitedUser($user)) {
            $band->acceptInvitation($user);
            
            Notification::make()
                ->title('Invitation accepted')
                ->body("Welcome to {$band->name}!")
                ->success()
                ->send();
            
            $this->redirect(route('filament.member.resources.band-profiles.view', ['record' => $band->id]));
        }
    }

    public function declineInvitation(int $bandId): void
    {
        $band = BandProfile::findOrFail($bandId);
        $user = auth()->user();
        
        if ($band->hasInvitedUser($user)) {
            $band->declineInvitation($user);
            
            Notification::make()
                ->title('Invitation declined')
                ->body('You have declined the invitation')
                ->success()
                ->send();
            
            // Redirect to band profiles index if no more invitations
            $remainingInvitations = $this->getPendingInvitations();
            if ($remainingInvitations->isEmpty()) {
                $this->redirect(route('filament.member.resources.band-profiles.index'));
            }
        }
    }
}
