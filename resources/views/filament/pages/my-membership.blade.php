<x-filament-panels::page>
    @php
        $user = \App\Models\User::me();
        $isSustainingMember = $user->isSustainingMember();
        $stats = \App\Actions\Subscriptions\GetSubscriptionStats::run();
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

    {{-- Current Membership Status (for sustaining members) --}}
    @if($isSustainingMember)
        <div class="mb-8">
            {{ $this->form }}
        </div>
    @endif

    {{-- Benefits Section --}}
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
