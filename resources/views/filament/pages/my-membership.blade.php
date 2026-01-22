<x-filament-panels::page>
    @php
        $user = \App\Models\User::me();
        $isSustainingMember = $user->isSustainingMember();
        $stats = \CorvMC\Finance\Actions\Subscriptions\GetSubscriptionStats::run();
    @endphp
    {{-- Hero Section --}}
    <div class="mb-8 rounded-xl bg-primary/10 p-8 text-center">
        <h1 class="mb-4 text-4xl font-bold text-gray-900 dark:text-white">
            {{ $isSustainingMember ? 'Thank You for Being a Sustaining Member!' : 'Become a Sustaining Member' }}
        </h1>
        <p class="mx-auto mb-6 max-w-2xl text-lg text-gray-700 dark:text-gray-300">
            {{ $isSustainingMember
                ? 'Your monthly contribution makes our community stronger. Explore your benefits below and see the impact you\'re making.'
                : 'Support our music community with a monthly contribution and unlock exclusive benefits including free practice hours, equipment credits, and more!'
            }}
        </p>

        @if(!$isSustainingMember)
            <div class="flex justify-center gap-4">
                {{ ($this->create_membership_subscription)(['record' => $user]) }}
            </div>
        @endif
    </div>

    {{-- Cancelled Membership Section --}}
    @if(!$isSustainingMember)
        @php
            $subscription = $user->subscription();
            $isCancelled = $subscription?->ends_at !== null;
        @endphp

        @if($isCancelled)
            <div class="mb-8 rounded-lg border border-warning-200 bg-warning-50 p-6 dark:border-warning-700 dark:bg-warning-900/20">
                <div class="mb-4">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Cancelled Sustaining Membership</h2>
                    <p class="mt-2 text-gray-700 dark:text-gray-300">
                        @if($subscription->ends_at)
                            Your contribution is cancelled and will end on <strong>{{ $subscription->ends_at->format('F j, Y \a\t g:i A') }}</strong>.
                            You can resume your contribution anytime before then. You will remain a member of the collective.
                        @else
                            Your contribution has been cancelled. You remain a member of the collective.
                        @endif
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Contribution Status</div>
                        @php
                            $price = \Laravel\Cashier\Cashier::stripe()->prices->retrieve($subscription->stripe_price);
                            $amount = \Brick\Money\Money::ofMinor($price->unit_amount, 'USD');
                        @endphp
                        <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $amount->formatTo('en_US') }}/{{ $price->recurring->interval }} - Ends {{ $subscription->ends_at->diffForHumans() }}
                        </div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Benefits Until Cancellation</div>
                        @php
                            $totalHours = \CorvMC\Finance\Actions\MemberBenefits\GetUserMonthlyFreeHours::run($user);
                        @endphp
                        <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $totalHours }} free hours/month until {{ $subscription->ends_at->format('M j, Y') }}
                        </div>
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Free Hours Remaining</div>
                        @php
                            $remaining = $user->getRemainingFreeHours();
                            $used = $user->getUsedFreeHoursThisMonth();
                        @endphp
                        <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $remaining }} hours ({{ $used }} used)
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex gap-3">
                    {{ $this->resumeMembershipAction }}
                    @if($this->getBillingPortalUrl())
                        <x-filament::button
                            tag="a"
                            :href="$this->getBillingPortalUrl()"
                            color="info"
                            icon="tabler-credit-card"
                        >
                            Manage Billing
                        </x-filament::button>
                    @endif
                </div>
            </div>
        @endif
    @endif

    {{-- Your Membership Benefits (for sustaining members) --}}
    @if($isSustainingMember)
        @php
            $totalHours = \CorvMC\Finance\Actions\MemberBenefits\GetUserMonthlyFreeHours::run($user);
            $remainingHours = $user->getRemainingFreeHours();
            $usedHours = $user->getUsedFreeHoursThisMonth();
            $subscription = $user->subscription();
            $nextBillingDate = null;
            $contributionAmount = 'Amount unavailable';
            $hasFeeCovered = false;

            if ($subscription) {
                try {
                    $stripeSubscription = $subscription->asStripeSubscription();
                    $nextBillingDate = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end)->format('F j, Y');

                    // Get contribution amount
                    $firstItem = $stripeSubscription->items->data[0];
                    $price = $firstItem->price;
                    $baseAmount = \Brick\Money\Money::ofMinor($price->unit_amount, 'USD');

                    // Check if user has fee coverage
                    $hasFeeCovered = count($stripeSubscription->items->data) > 1;
                    if ($hasFeeCovered) {
                        $totalAmount = collect($stripeSubscription->items->data)
                            ->sum(fn ($item) => $item->price->unit_amount);
                        $totalCost = \Brick\Money\Money::ofMinor($totalAmount, 'USD');
                        $contributionAmount = sprintf('%s/%s', $totalCost->formatTo('en_US'), $price->recurring->interval);
                    } else {
                        $contributionAmount = sprintf('%s/%s', $baseAmount->formatTo('en_US'), $price->recurring->interval);
                    }
                } catch (\Exception $e) {}
            }
        @endphp

        <div class="mb-8">
            <div class="mb-6 text-center">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Your Membership Benefits</h2>
                <p class="mt-2 text-gray-600 dark:text-gray-400">Track and manage your sustaining member perks</p>
            </div>

            <div class="space-y-6">
                {{-- Contribution Card --}}
                <div class="rounded-lg border border-gray-200 bg-white p-8 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="mb-6 flex items-center gap-4">
                        <div class="flex size-16 items-center justify-center rounded-full bg-primary/10">
                            <x-heroicon-o-credit-card class="size-8 text-primary-600" />
                        </div>
                        <div class="flex-1">
                            <h3 class="text-2xl font-semibold text-gray-900 dark:text-white">Your Membership Contribution</h3>
                            <p class="text-gray-600 dark:text-gray-400">Manage your monthly support</p>
                        </div>
                    </div>

                    <div class="mb-6 flex items-center justify-between rounded-lg bg-gray-50 p-6 dark:bg-gray-700/50">
                        <div>
                            <div class="mb-1 flex items-center gap-2">
                                <span class="text-4xl font-bold text-gray-900 dark:text-white">{{ $contributionAmount }}</span>
                                @if($hasFeeCovered)
                                    <x-heroicon-s-heart class="size-6 text-danger-600" />
                                @endif
                            </div>
                            @if($nextBillingDate)
                                <p class="text-sm text-gray-600 dark:text-gray-400">Next bill {{ $nextBillingDate }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="flex gap-3">
                        @if($this->getBillingPortalUrl())
                            <x-filament::button
                                tag="a"
                                :href="$this->getBillingPortalUrl()"
                                color="info"
                                icon="tabler-credit-card"
                            >
                                Manage Billing
                            </x-filament::button>
                        @endif

                        {{ $this->modifyMembershipAmountAction   }}
                    </div>
                </div>
                {{-- Free Practice Hours Card --}}
                <div class="rounded-lg border border-gray-200 bg-white p-8 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="mb-6 flex items-center gap-4">
                        <div class="flex size-16 items-center justify-center rounded-full bg-primary/10">
                            <x-heroicon-o-musical-note class="size-8 text-primary-600" />
                        </div>
                        <div class="flex-1">
                            <h3 class="text-2xl font-semibold text-gray-900 dark:text-white">Free Practice Hours</h3>
                            <p class="text-gray-600 dark:text-gray-400">Your monthly allocation of free practice time</p>
                        </div>
                    </div>

                    <div class="mb-6 grid gap-6 md:grid-cols-3">
                        <div class="text-center rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                            <div class="mb-1 text-4xl font-bold text-primary-600">{{ $totalHours }}</div>
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Hours This Month</div>
                        </div>
                        <div class="text-center rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                            <div class="mb-1 text-4xl font-bold {{ $remainingHours > 0 ? 'text-success-600' : 'text-warning-600' }}">{{ $remainingHours }}</div>
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Hours Remaining</div>
                        </div>
                        <div class="text-center rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                            <div class="mb-1 text-4xl font-bold text-gray-700 dark:text-gray-300">{{ $usedHours }}</div>
                            <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Hours Used</div>
                        </div>
                    </div>

                    @if($nextBillingDate)
                        <div class="rounded-lg bg-primary-50 p-4 text-center dark:bg-primary-900/20">
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                Your hours will refresh on <strong>{{ $nextBillingDate }}</strong>
                            </p>
                        </div>
                    @endif

                    <div class="mt-6 space-y-2 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <div class="flex items-start gap-2 text-gray-700 dark:text-gray-300">
                            <x-heroicon-s-check-circle class="mt-0.5 size-5 flex-shrink-0 text-success-600" />
                            <span><strong>Recurring reservations</strong> to secure your regular practice times</span>
                        </div>
                        <div class="flex items-start gap-2 text-gray-700 dark:text-gray-300">
                            <x-heroicon-s-check-circle class="mt-0.5 size-5 flex-shrink-0 text-success-600" />
                            <span><strong>Priority booking</strong> for last-minute availability</span>
                        </div>
                    </div>
                </div>

                {{-- Equipment & Community Benefits Grid --}}
                <div class="grid gap-6 md:grid-cols-2">
                    {{-- Equipment Benefits --}}
                    <div class="rounded-lg border border-gray-200 bg-white p-8 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="mb-6 flex items-center gap-4">
                            <div class="flex size-16 items-center justify-center rounded-full bg-secondary/10">
                                <x-heroicon-o-wrench-screwdriver class="size-8 text-secondary-600" />
                            </div>
                            <div class="flex-1">
                                <h3 class="text-2xl font-semibold text-gray-900 dark:text-white">Equipment Access</h3>
                                <p class="text-gray-600 dark:text-gray-400">Free and discounted gear rentals</p>
                            </div>
                        </div>

                        <ul class="space-y-3 text-gray-700 dark:text-gray-300">
                            <li class="flex items-start gap-2">
                                <x-heroicon-s-check-circle class="mt-0.5 size-5 flex-shrink-0 text-success-600" />
                                <span><strong>Free accessory rentals</strong> (cables, stands, mic clips, etc.)</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <x-heroicon-s-check-circle class="mt-0.5 size-5 flex-shrink-0 text-success-600" />
                                <span><strong>Monthly equipment credits</strong> equal to your contribution</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <x-heroicon-s-check-circle class="mt-0.5 size-5 flex-shrink-0 text-success-600" />
                                <span><strong>Discounted rentals</strong> on premium gear</span>
                            </li>
                        </ul>
                    </div>

                    {{-- Community Benefits --}}
                    <div class="rounded-lg border border-gray-200 bg-white p-8 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="mb-6 flex items-center gap-4">
                            <div class="flex size-16 items-center justify-center rounded-full bg-accent/10">
                                <x-heroicon-o-users class="size-8 text-accent-600" />
                            </div>
                            <div class="flex-1">
                                <h3 class="text-2xl font-semibold text-gray-900 dark:text-white">Community Perks</h3>
                                <p class="text-gray-600 dark:text-gray-400">Exclusive member benefits</p>
                            </div>
                        </div>

                        <ul class="space-y-3 text-gray-700 dark:text-gray-300">
                            <li class="flex items-start gap-2">
                                <x-heroicon-s-check-circle class="mt-0.5 size-5 flex-shrink-0 text-success-600" />
                                <span><strong>50% off admission</strong> to all CMC events and shows</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <x-heroicon-s-check-circle class="mt-0.5 size-5 flex-shrink-0 text-success-600" />
                                <span><strong>Early access</strong> to workshops and special events</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <x-heroicon-s-check-circle class="mt-0.5 size-5 flex-shrink-0 text-success-600" />
                                <span><strong>Support local music</strong> and help us grow the community</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Benefits Section (for non-sustaining members) --}}
    @if(!$isSustainingMember)
        <div class="mb-8">
            <div class="mb-6 text-center">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Sustaining Member Benefits</h2>
                <p class="mt-2 text-gray-600 dark:text-gray-400">Everything you get with a monthly contribution</p>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                {{-- Practice Space Benefits --}}
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="mb-4 flex size-12 items-center justify-center rounded-full bg-primary/10">
                        <x-heroicon-o-musical-note class="size-6 text-primary-600" />
                    </div>
                    <h3 class="mb-3 text-xl font-semibold text-gray-900 dark:text-white">Practice Space</h3>
                    <ul class="space-y-2 text-gray-700 dark:text-gray-300">
                        <li class="flex items-start gap-2">
                            <x-heroicon-s-check-circle class="mt-0.5 size-5 flex-shrink-0 text-success-600" />
                            <span><strong>1 free hour for every $5</strong> you contribute each month</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <x-heroicon-s-check-circle class="mt-0.5 size-5 flex-shrink-0 text-success-600" />
                            <span><strong>Recurring reservations</strong> to secure your regular practice times</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <x-heroicon-s-check-circle class="mt-0.5 size-5 flex-shrink-0 text-success-600" />
                            <span><strong>Priority booking</strong> for last-minute availability</span>
                        </li>
                    </ul>
                </div>

                {{-- Equipment Benefits --}}
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="mb-4 flex size-12 items-center justify-center rounded-full bg-secondary/10">
                        <x-heroicon-o-wrench-screwdriver class="size-6 text-secondary-600" />
                    </div>
                    <h3 class="mb-3 text-xl font-semibold text-gray-900 dark:text-white">Equipment Access</h3>
                    <ul class="space-y-2 text-gray-700 dark:text-gray-300">
                        <li class="flex items-start gap-2">
                            <x-heroicon-s-check-circle class="mt-0.5 size-5 flex-shrink-0 text-success-600" />
                            <span><strong>Free accessory rentals</strong> (cables, stands, mic clips, etc.)</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <x-heroicon-s-check-circle class="mt-0.5 size-5 flex-shrink-0 text-success-600" />
                            <span><strong>Monthly equipment credits</strong> equal to your contribution</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <x-heroicon-s-check-circle class="mt-0.5 size-5 flex-shrink-0 text-success-600" />
                            <span><strong>Discounted rentals</strong> on premium gear</span>
                        </li>
                    </ul>
                </div>

                {{-- Community Benefits --}}
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="mb-4 flex size-12 items-center justify-center rounded-full bg-accent/10">
                        <x-heroicon-o-users class="size-6 text-accent-600" />
                    </div>
                    <h3 class="mb-3 text-xl font-semibold text-gray-900 dark:text-white">Community Perks</h3>
                    <ul class="space-y-2 text-gray-700 dark:text-gray-300">
                        <li class="flex items-start gap-2">
                            <x-heroicon-s-check-circle class="mt-0.5 size-5 flex-shrink-0 text-success-600" />
                            <span><strong>50% off admission</strong> to all CMC events and shows</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <x-heroicon-s-check-circle class="mt-0.5 size-5 flex-shrink-0 text-success-600" />
                            <span><strong>Early access</strong> to workshops and special events</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <x-heroicon-s-check-circle class="mt-0.5 size-5 flex-shrink-0 text-success-600" />
                            <span><strong>Support local music</strong> and help us grow the community</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Sliding Scale Contribution --}}
    @if(!$isSustainingMember)
        <div class="mb-8">
            <div class="mb-6 text-center">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Sliding Scale Contributions</h2>
                <p class="mt-2 text-gray-600 dark:text-gray-400">Choose any amount from $10-$60/month that works for your budget</p>
            </div>

            <div class="mx-auto max-w-4xl rounded-lg border border-gray-200 bg-white p-8 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-8 text-center">
                    <div class="mb-4 text-6xl font-bold text-primary-600">1 hour = $5</div>
                    <p class="text-lg text-gray-700 dark:text-gray-300">
                        For every $5 you contribute each month, you receive 1 free practice hour
                    </p>
                </div>

                <div class="mb-8 space-y-4">
                    <div class="flex items-center justify-between rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                        <span class="text-gray-900 dark:text-white">$10/month</span>
                        <span class="text-gray-600 dark:text-gray-400">→</span>
                        <span class="font-semibold text-gray-900 dark:text-white">2 free hours</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                        <span class="text-gray-900 dark:text-white">$25/month</span>
                        <span class="text-gray-600 dark:text-gray-400">→</span>
                        <span class="font-semibold text-gray-900 dark:text-white">5 free hours</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                        <span class="text-gray-900 dark:text-white">$50/month</span>
                        <span class="text-gray-600 dark:text-gray-400">→</span>
                        <span class="font-semibold text-gray-900 dark:text-white">10 free hours</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-gray-50 p-4 dark:bg-gray-700/50">
                        <span class="text-gray-900 dark:text-white">$60/month</span>
                        <span class="text-gray-600 dark:text-gray-400">→</span>
                        <span class="font-semibold text-gray-900 dark:text-white">12 free hours</span>
                    </div>
                </div>

                <div class="rounded-lg bg-primary-50 p-6 dark:bg-primary-900/20">
                    <div class="mb-2 text-center text-sm font-semibold uppercase tracking-wide text-primary-800 dark:text-primary-200">
                        Why Sliding Scale?
                    </div>
                    <p class="text-center text-gray-700 dark:text-gray-300">
                        We believe everyone should have access to music resources, regardless of their financial situation.
                        Contribute what makes sense for your budget — every amount helps sustain our community and
                        provides the exact same great benefits.
                    </p>
                </div>

                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        💡 All contributions are flexible! Change or cancel anytime, no questions asked.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Impact Stats Section --}}
    <div class="mb-8 rounded-lg bg-gray-50 p-8 dark:bg-gray-800/50">
        <div class="mb-6 text-center">
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Community Impact</h2>
            <p class="mt-2 text-gray-600 dark:text-gray-400">See how sustaining members are supporting our community</p>
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            <div class="text-center">
                <div class="mb-2 text-4xl font-bold text-primary-600">{{ $stats->sustaining_members }}</div>
                <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Sustaining Members</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-500">Supporting our mission monthly</div>
            </div>
            <div class="text-center">
                <div class="mb-2 text-4xl font-bold text-secondary-600">{{ number_format($stats->total_free_hours_allocated) }}</div>
                <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Free Hours Allocated</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-500">Practice time provided each month</div>
            </div>
            <div class="text-center">
                <div class="mb-2 text-4xl font-bold text-accent-600">{{ number_format(($stats->sustaining_members / max($stats->total_users, 1)) * 100, 1) }}%</div>
                <div class="text-sm font-medium text-gray-600 dark:text-gray-400">Member Participation</div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-500">Of all members contributing</div>
            </div>
        </div>
    </div>

    {{-- FAQ Section --}}
    <div class="mb-8">
        <div class="mb-6 text-center">
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Frequently Asked Questions</h2>
        </div>

        <div class="mx-auto max-w-3xl space-y-4">
            <details class="group rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <summary class="cursor-pointer p-4 font-semibold text-gray-900 dark:text-white">
                    How do the free practice hours work?
                </summary>
                <div class="border-t border-gray-200 p-4 text-gray-700 dark:border-gray-700 dark:text-gray-300">
                    For every $5 you contribute monthly, you receive 1 free practice hour. These hours are automatically added to your account at the beginning of each billing cycle and can be used anytime during that month. Unused hours don't roll over, but you'll get fresh hours each month as long as your contribution is active.
                </div>
            </details>

            <details class="group rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <summary class="cursor-pointer p-4 font-semibold text-gray-900 dark:text-white">
                    Can I change my contribution amount later?
                </summary>
                <div class="border-t border-gray-200 p-4 text-gray-700 dark:border-gray-700 dark:text-gray-300">
                    Absolutely! You can change your contribution amount anytime through your membership dashboard. The new amount will take effect at your next billing cycle, and your free hours will adjust accordingly. You can also pause or cancel your contribution at any time with no penalties.
                </div>
            </details>

            <details class="group rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <summary class="cursor-pointer p-4 font-semibold text-gray-900 dark:text-white">
                    What happens if I cancel my contribution?
                </summary>
                <div class="border-t border-gray-200 p-4 text-gray-700 dark:border-gray-700 dark:text-gray-300">
                    You'll continue to have access to all your sustaining member benefits until the end of your current billing period. After that, you'll remain a regular member of the collective and can still book practice space at our standard $15/hour rate. You can resume your contribution at any time to regain all the benefits.
                </div>
            </details>

            <details class="group rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <summary class="cursor-pointer p-4 font-semibold text-gray-900 dark:text-white">
                    Can I use my free hours for my band?
                </summary>
                <div class="border-t border-gray-200 p-4 text-gray-700 dark:border-gray-700 dark:text-gray-300">
                    Yes! Your free hours can be used for solo practice, band rehearsals, or any other approved use of our practice space. As long as you're the one making the reservation, you can use your hours however you'd like.
                </div>
            </details>

            <details class="group rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <summary class="cursor-pointer p-4 font-semibold text-gray-900 dark:text-white">
                    What are recurring reservations?
                </summary>
                <div class="border-t border-gray-200 p-4 text-gray-700 dark:border-gray-700 dark:text-gray-300">
                    Sustaining members can set up recurring reservations to automatically book the same time slot each week. This is perfect for bands with regular practice schedules or musicians who prefer consistent practice times. You can manage your recurring reservations from your dashboard.
                </div>
            </details>

            <details class="group rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <summary class="cursor-pointer p-4 font-semibold text-gray-900 dark:text-white">
                    How do equipment credits work?
                </summary>
                <div class="border-t border-gray-200 p-4 text-gray-700 dark:border-gray-700 dark:text-gray-300">
                    Your monthly equipment credits match your contribution amount in dollars. For example, a $25/month contribution gives you $25 in equipment credits each month. These can be used to rent premium gear, with accessories like cables and stands being completely free for sustaining members.
                </div>
            </details>
        </div>
    </div>

    {{-- Sign Up Section (for non-sustaining members) --}}
    @if(!$isSustainingMember)
        <div class="rounded-xl bg-primary-50 p-8 text-center dark:bg-primary-900/20">
            <h2 class="mb-4 text-3xl font-bold text-gray-900 dark:text-white">Ready to Make an Impact?</h2>
            <p class="mx-auto mb-6 max-w-2xl text-lg text-gray-700 dark:text-gray-300">
                Join {{ $stats->sustaining_members }} other members who are supporting our music community with a monthly contribution. Every dollar helps us maintain our space, upgrade equipment, and keep music accessible to everyone.
            </p>
            {{ ($this->create_membership_subscription)(['record' => $user]) }}
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                Cancel anytime, no questions asked. We're grateful for whatever support you can provide.
            </p>
        </div>
    @endif
</x-filament-panels::page>
