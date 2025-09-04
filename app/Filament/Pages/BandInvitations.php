<?php

namespace App\Filament\Pages;

use App\Models\Band;
use App\Models\User;
use App\Services\BandService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

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
        return app(BandService::class)->getPendingInvitationsForUser(auth()->user());
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
        $bandService = app(BandService::class);
        $band = Band::findOrFail($bandId);
        $user = User::me();

        if ($bandService->acceptInvitation($band, $user)) {
            Notification::make()
                ->title('Invitation accepted')
                ->body("Welcome to {$band->name}!")
                ->success()
                ->send();

            $this->redirect(route('filament.member.resources.bands.view', ['record' => $band]));
        }
    }

    public function declineInvitationAction(): Action
    {
        return Action::make('declineInvitation')
            ->label('Decline')
            ->color('gray')
            ->icon('heroicon-s-x-mark')
            ->requiresConfirmation()
            ->modalHeading('Decline Band Invitation')
            ->modalDescription(fn (array $arguments) => "Are you sure you want to decline the invitation to join " . Band::find($arguments['bandId'])->name . "?")
            ->modalSubmitActionLabel('Yes, decline')
            ->modalCancelActionLabel('Cancel')
            ->action(function (array $arguments): void {
                $bandService = app(BandService::class);
                $band = Band::findOrFail($arguments['bandId']);
                $user = auth()->user();

                if ($bandService->declineInvitation($band, $user)) {
                    Notification::make()
                        ->title('Invitation declined')
                        ->body('You have declined the invitation')
                        ->success()
                        ->send();

                    // Redirect to band profiles index if no more invitations
                    $remainingInvitations = $this->getPendingInvitations();
                    if ($remainingInvitations->isEmpty()) {
                        $this->redirect(route('filament.member.resources.bands.index'));
                    }
                }
            });
    }
}
