<?php

namespace App\Filament\Pages;

use App\Actions\Bands\AcceptBandInvitation;
use App\Actions\Bands\DeclineBandInvitation;
use App\Models\Band;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class BandInvitations extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'tabler-mail';

    protected string $view = 'filament.pages.band-invitations';

    protected static ?string $navigationLabel = 'Band Invitations';

    protected static ?int $navigationSort = 25;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        // Check if user has any pending invitations
        $invitations = $this->getPendingInvitations();

        if ($invitations->isEmpty()) {
            $this->redirect(route('filament.member.resources.bands.index'));

            return;
        }
    }

    public function getPendingInvitations(): Collection
    {
        return User::me()->bandMemberships()->invited()->get();
    }

    protected function getActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon('tabler-refresh')
                ->action(fn() => $this->redirect(request()->url())),
        ];
    }

    public function acceptInvitation(int $bandId): void
    {
        $band = Band::findOrFail($bandId);
        $user = Auth::user();

        AcceptBandInvitation::run($band, $user);

        Notification::make()
            ->title('Invitation accepted')
            ->body("Welcome to {$band->name}!")
            ->success()
            ->send();

        $this->redirect(route('filament.member.resources.bands.view', ['record' => $band]));
    }

    public function declineInvitationAction(): Action
    {
        return DeclineBandInvitation::filamentAction();
    }
}
