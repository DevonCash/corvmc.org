<?php

namespace App\Filament\Member\Resources\Reservations\Widgets;

use App\Models\User;
use CorvMC\Finance\Actions\MemberBenefits\GetUserMonthlyFreeHours;
use Filament\Widgets\Widget;

class FreeHoursWidget extends Widget
{
    protected string $view = 'space-management::filament.widgets.free-hours-widget';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public bool $isSustainingMember = false;

    public float $remainingHours = 0;

    public float $usedHours = 0;

    public float $monthlyGrantHours = 0;

    public function mount(): void
    {
        $user = User::me();
        $this->isSustainingMember = $user?->hasRole('sustaining member') ?? false;

        if ($this->isSustainingMember) {
            $this->remainingHours = $user->getRemainingFreeHours();
            $this->usedHours = $user->getUsedFreeHoursThisMonth();
            $this->monthlyGrantHours = (float) GetUserMonthlyFreeHours::run($user);
        }
    }
}
