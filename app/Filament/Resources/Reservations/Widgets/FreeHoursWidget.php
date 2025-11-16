<?php

namespace App\Filament\Resources\Reservations\Widgets;

use App\Enums\CreditType;
use App\Models\CreditTransaction;
use App\Models\Reservation;
use Filament\Widgets\Widget;

class FreeHoursWidget extends Widget
{
    protected string $view = 'filament.resources.reservations.widgets.free-hours-widget';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public bool $isSustainingMember = false;

    public float $remainingHours = 0;

    public float $usedHours = 0;

    public float $totalAllocatedHours = 0;

    public ?string $allocationDate = null;

    public function mount(): void
    {
        $user = auth()->user();
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
