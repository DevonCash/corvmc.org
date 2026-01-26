<?php

namespace App\Filament\Member\Resources\Reservations\Widgets;

use CorvMC\Finance\Enums\CreditType;
use CorvMC\Finance\Models\CreditTransaction;
use CorvMC\SpaceManagement\Models\Reservation;
use App\Models\User;
use Filament\Widgets\Widget;

class FreeHoursWidget extends Widget
{
    protected string $view = 'space-management::filament.widgets.free-hours-widget';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public bool $isSustainingMember = false;

    public float $remainingHours = 0;

    public float $usedHours = 0;

    public float $totalAllocatedHours = 0;

    public ?string $allocationDate = null;

    public function mount(): void
    {
        $user = User::me();
        $this->isSustainingMember = $user?->hasRole('sustaining member') ?? false;

        if ($this->isSustainingMember) {
            $this->remainingHours = $user->getRemainingFreeHours();
            $this->usedHours = $user->getUsedFreeHoursThisMonth();

            // Get total allocated hours from last allocation transaction
            $lastAllocation = CreditTransaction::where('user_id', $user->id)
                ->where('credit_type', CreditType::FreeHours->value)
                ->whereIn('source', ['monthly_reset', 'monthly_allocation', 'upgrade_adjustment'])
                ->latest('created_at')
                ->first();

            if ($lastAllocation) {
                $this->allocationDate = $lastAllocation->created_at->format('M j, Y');

                if ($lastAllocation->metadata) {
                    $metadata = is_array($lastAllocation->metadata)
                        ? $lastAllocation->metadata
                        : json_decode($lastAllocation->metadata, true);

                    $allocatedBlocks = $metadata['allocated_amount'] ?? 0;
                    $this->totalAllocatedHours = Reservation::blocksToHours($allocatedBlocks);
                }
            }
        }
    }
}
