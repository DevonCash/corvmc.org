<?php

namespace App\Filament\Band\Pages;

use App\Filament\Band\Resources\BandMembersResource;
use App\Filament\Band\Pages\EditBandProfile;
use CorvMC\Bands\Models\Band;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;

class BandDashboard extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-home';

    protected string $view = 'bands::filament.pages.band-dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -100;

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public function getTitle(): string
    {
        /** @var Band $band */
        $band = Filament::getTenant();

        return $band->name;
    }

    public function getBand(): Band
    {
        /** @var Band $band */
        $band = Filament::getTenant();

        return $band;
    }

    public function getBandStats(): array
    {
        $band = $this->getBand();

        return [
            'active_members' => $band->activeMembers()->count(),
            'pending_invitations' => $band->pendingInvitations()->count(),
        ];
    }

    public function getQuickActions(): array
    {
        return [
            [
                'label' => 'Manage Members',
                'description' => 'Invite or manage band members',
                'icon' => 'tabler-users',
                'color' => 'info',
                'url' => BandMembersResource::getUrl(),
            ],
            [
                'label' => 'Edit Profile',
                'description' => 'Update band info and photos',
                'icon' => 'tabler-edit',
                'color' => 'gray',
                'url' => EditBandProfile::getUrl(),
            ],
        ];
    }
}
