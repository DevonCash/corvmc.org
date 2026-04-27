<x-filament-panels::page>
    <div class="space-y-8">
        {{-- Submission Form --}}
        <x-filament::section heading="Report Volunteer Hours">
            <form wire:submit="submit">
                {{ $this->form }}

                <div class="mt-4">
                    <x-filament::button type="submit">
                        Submit for Review
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        {{-- Submission History --}}
        @php($submissions = $this->getSubmissions())
        @if($submissions->isNotEmpty())
            <x-filament::section heading="My Submissions">
                <div class="overflow-x-auto">
                    <table class="w-full text-start divide-y divide-gray-200 dark:divide-white/5">
                        <thead>
                            <tr>
                                <th class="px-3 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-start">Position</th>
                                <th class="px-3 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-start">Date</th>
                                <th class="px-3 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-end">Minutes</th>
                                <th class="px-3 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-start">Status</th>
                                <th class="px-3 py-3.5 text-sm font-semibold text-gray-950 dark:text-white text-start">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                            @foreach($submissions as $log)
                                <tr>
                                    <td class="px-3 py-4 text-sm text-gray-950 dark:text-white">
                                        {{ $log->position->title }}
                                    </td>
                                    <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $log->started_at->format('M j, Y') }}
                                    </td>
                                    <td class="px-3 py-4 text-sm text-gray-950 dark:text-white text-end">
                                        {{ $log->minutes ?? '—' }}
                                    </td>
                                    <td class="px-3 py-4 text-sm">
                                        <x-filament::badge :color="$log->status->getColor()" size="sm">
                                            {{ $log->status->getLabel() }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ $log->notes ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
