<?php

namespace App\Filament\Resources\Users\Widgets;

use App\Actions\Subscriptions\GetSubscriptionStats;
use Filament\Widgets\Widget;

class UserStatsWidget extends Widget
{
    protected int | string | array $columnSpan = 'full';
    protected string $view = 'filament.resources.users.widgets.user-stats-widget';

    protected static bool $isLazy = false;

    public int $totalMembers = 0;

    public int $sustainingMembers = 0;

    public int $activeSubscriptions = 0;

    public int $newMembersThisMonth = 0;

    public int $subscriptionNetChangeLastMonth = 0;

    public string $mrrTotal = '$0.00';

    public string $mrrBase = '$0.00';

    public string $feeCost = '$0.00';

    public string $averageMrr = '$0.00';

    public string $medianContribution = '$0.00';

    public function mount(): void
    {
        $stats = GetSubscriptionStats::run();

        $this->totalMembers = $stats->total_users;
        $this->sustainingMembers = $stats->sustaining_members;
        $this->activeSubscriptions = $stats->active_subscriptions_count;
        $this->newMembersThisMonth = $stats->new_members_this_month;
        $this->subscriptionNetChangeLastMonth = $stats->subscription_net_change_last_month;
        $this->mrrTotal = $stats->mrr_total->formatTo('en_US');
        $this->mrrBase = $stats->mrr_base->formatTo('en_US');
        $this->feeCost = $stats->mrr_total->minus($stats->mrr_base)->formatTo('en_US');
        $this->averageMrr = $stats->average_mrr->formatTo('en_US');
        $this->medianContribution = $stats->median_contribution->formatTo('en_US');
    }
}
