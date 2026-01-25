<?php

namespace App\Filament\Band\Pages;

use App\Filament\Band\Resources\BandMembersResource;
use App\Filament\Band\Resources\BandReservationsResource;
use App\Filament\Pages\Tenancy\EditBandProfile;
use App\Models\Band;
use CorvMC\SpaceManagement\Models\Reservation;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Panel;

class BandDashboard extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-home';

    protected string $view = 'filament.band.pages.band-dashboard';

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
            'upcoming_reservations' => $band->morphMany(Reservation::class, 'reservable')
                ->where('reserved_at', '>', now())
                ->count(),
        ];
    }

    public function getUpcomingReservations()
    {
        $band = $this->getBand();

        return Reservation::query()
            ->where('reservable_type', Band::class)
            ->where('reservable_id', $band->id)
            ->where('reserved_at', '>', now())
            ->orderBy('reserved_at')
            ->limit(5)
            ->get();
    }

    public function getQuickActions(): array
    {
        return [
            [
                'label' => 'Book Practice Space',
                'description' => 'Reserve a room for rehearsal',
                'icon' => 'tabler-calendar-plus',
                'color' => 'primary',
                'url' => BandReservationsResource::getUrl('create'),
            ],
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
