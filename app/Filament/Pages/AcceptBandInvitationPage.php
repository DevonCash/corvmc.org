<?php

namespace App\Filament\Pages;

use CorvMC\Membership\Actions\Bands\AcceptBandInvitation;
use CorvMC\Membership\Actions\Bands\DeclineBandInvitation;
use CorvMC\Bands\Models\Band;
use CorvMC\Bands\Models\BandMember;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;

class AcceptBandInvitationPage extends Page implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.accept-band-invitation';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'band-invitation/{band}';

    public Band $band;
    public BandMember $membership;

    public function mount(Band $band): void
    {
        $user = User::me();

        // Get the invitation record
        $invitation = $band->memberships()
            ->invited()
            ->where('user_id', $user->id)
            ->first();

        if (!$invitation) {
            Notification::make()
                ->warning()
                ->title('Invitation Not Found')
                ->body('This invitation may have already been processed.')
                ->send();

            $this->redirect(route('filament.member.resources.bands.index'));
            return;
        }

        $this->band = $band;
        $this->membership = $invitation;
    }

    public function acceptAction(): Action
    {
        return AcceptBandInvitation::filamentAction()
            ->record($this->membership)
            ->after(function () {
                $this->redirect("/band/{$this->band->slug}");
            });
    }

    public function declineAction(): Action
    {
        return DeclineBandInvitation::filamentAction()
            ->record($this->membership)
            ->after(function () {
                $this->redirect(route('filament.member.resources.bands.index'));
            });
    }

    public function getTitle(): string
    {
        return "Band Invitation: {$this->band->name}";
    }
}
