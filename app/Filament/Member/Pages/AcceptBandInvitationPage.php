<?php

namespace App\Filament\Member\Pages;

use CorvMC\Bands\Models\Band;
use Filament\Pages\Page;

/**
 * Legacy page that redirects to the new Band panel acceptance page.
 * Kept for backward compatibility with old invitation links.
 */
class AcceptBandInvitationPage extends Page
{
    protected string $view = 'filament.pages.accept-band-invitation';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'band-invitation/{band}';

    public function mount(Band $band): void
    {
        // Redirect to the new Band panel acceptance page
        $this->redirect("/band/{$band->slug}/accept-invitation");
    }
}
