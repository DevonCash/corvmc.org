<?php

namespace App\Filament\Band\Pages;

use App\Models\User;
use CorvMC\Bands\Http\Middleware\EnsureActiveBandMembership;
use CorvMC\Bands\Models\Band;
use CorvMC\Bands\Models\BandMember;
use CorvMC\Membership\Actions\Bands\AcceptBandInvitation;
use CorvMC\Membership\Actions\Bands\DeclineBandInvitation;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;

class AcceptInvitationPage extends Page implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament.band.pages.accept-invitation';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'accept-invitation';

    /**
     * Bypass the tenant membership middleware for this page.
     * This allows invited users (who aren't yet members) to view the page.
     *
     * @var string|array<class-string>
     */
    protected static string|array $withoutRouteMiddleware = [
        EnsureActiveBandMembership::class,
    ];

    public Band $band;

    public BandMember $membership;

    public function mount(): void
    {
        /** @var Band $band */
        $band = Filament::getTenant();
        $user = User::me();

        // Check if user is already an active member
        $activeMembership = $band->memberships()
            ->active()
            ->where('user_id', $user->id)
            ->exists();

        if ($activeMembership) {
            Notification::make()
                ->info()
                ->title('Already a Member')
                ->body("You're already a member of {$band->name}.")
                ->send();

            $this->redirect("/band/{$band->slug}");

            return;
        }

        // Get the invitation record
        $invitation = $band->memberships()
            ->invited()
            ->where('user_id', $user->id)
            ->first();

        if (! $invitation) {
            Notification::make()
                ->warning()
                ->title('Invitation Not Found')
                ->body('This invitation may have already been processed or you were not invited to this band.')
                ->send();

            $this->redirect(route('filament.member.pages.member-dashboard'));

            return;
        }

        $this->band = $band;
        $this->membership = $invitation;
    }

    public function acceptAction(): Action
    {
        return AcceptBandInvitation::filamentAction()
            ->name('accept')
            ->record($this->membership)
            ->after(function () {
                $this->redirect("/band/{$this->band->slug}");
            });
    }

    public function declineAction(): Action
    {
        return DeclineBandInvitation::filamentAction()
            ->name('decline')
            ->record($this->membership)
            ->after(function () {
                $this->redirect(route('filament.member.pages.member-dashboard'));
            });
    }

    public function getTitle(): string
    {
        return "Join {$this->band->name}";
    }

    public function getLayout(): string
    {
        return 'filament-panels::components.layout.simple';
    }

    public function hasLogo(): bool
    {
        return true;
    }

    public function getBandStats(): array
    {
        return [
            'active_members' => $this->band->activeMembers()->count(),
            'visibility' => $this->band->visibility->getLabel(),
        ];
    }

    public function getRoleCapabilities(): array
    {
        if ($this->membership->role === 'admin') {
            return [
                'Manage band profile and settings',
                'Invite and remove band members',
                'Book practice space for the band',
                'Manage band reservations',
            ];
        }

        return [
            'View band profile and members',
            'Book practice space for the band',
            'Represent the band at events',
        ];
    }
}
