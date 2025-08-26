@php
    $contextTitle = match($context['type']) {
        'band' => $context['record_id'] ? 'Band Activity' : 'All Bands',
        'member' => $context['record_id'] ? 'Member Activity' : 'Member Updates',
        'reservation' => 'Practice Space Activity',
        'production' => $context['record_id'] ? 'Event Activity' : 'All Events',
        default => 'Recent Activity',
    };
@endphp

<div x-data="{ sidebarOpen: false }" @toggle-activity-sidebar.window="sidebarOpen = !sidebarOpen" class="relative" x-init="$nextTick(() => { /* Prevent flash by ensuring initial state is set */ })">
    {{-- Overlay --}}
    <div 
        x-show="sidebarOpen"
        @click="sidebarOpen = false"
        class="fixed inset-0 bg-gray-900/50 dark:bg-gray-900/80 z-30"
        x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    ></div>

    {{-- Sidebar --}}
    <aside 
        class="fi-activity-sidebar w-80 border-l border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden flex flex-col transition-transform duration-300 ease-in-out"
        :class="sidebarOpen ? 'translate-x-0' : 'translate-x-full'"
        x-cloak
    >
    {{-- Header --}}
    <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-medium text-gray-900 dark:text-white">
                {{ $contextTitle }}
            </h2>
            @if($context['type'] !== 'dashboard')
                <span class="text-xs px-2 py-1 rounded-full bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300">
                    {{ ucfirst($context['type']) }}
                </span>
            @endif
            {{-- Close Button --}}
            <button 
                @click="sidebarOpen = false"
                class="p-1 hover:bg-gray-200 dark:hover:bg-gray-700 rounded"
            >
                <x-tabler-x class="w-4 h-4 text-gray-500" />
            </button>
        </div>
        @if($context['record_id'])
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                Showing activity for this {{ $context['type'] }}
            </p>
        @endif
    </div>

    {{-- Activity List --}}
    <div class="flex-1 overflow-y-auto">
        @if($activities->count() > 0)
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($activities as $activity)
                    @if($activity['url'])
                        <a href="{{ $activity['url'] }}" class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <div class="flex gap-3">
                                {{-- Icon --}}
                                <div class="flex-shrink-0 mt-0.5">
                                    @php
                                        $iconColorClass = match($activity['color']) {
                                            'success' => 'text-green-500',
                                            'info' => 'text-blue-500', 
                                            'danger' => 'text-red-500',
                                            default => 'text-gray-400',
                                        };
                                    @endphp
                                    <x-dynamic-component 
                                        :component="'tabler-' . str_replace('tabler-', '', $activity['icon'])"
                                        class="w-4 h-4 {{ $iconColorClass }}"
                                    />
                                </div>

                                {{-- Content --}}
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900 dark:text-white leading-relaxed">
                                        {{ $activity['description'] }}
                                    </p>
                                    <div class="flex items-center justify-between mt-1">
                                        <span class="text-xs text-gray-600 dark:text-gray-400">
                                            {{ $activity['created_at']->diffForHumans() }}
                                        </span>
                                        <div class="flex items-center gap-2">
                                            @if($activity['subject_type'] && $activity['subject_id'])
                                                <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                                                    {{ $activity['subject_type'] }}
                                                </span>
                                            @endif
                                            <x-tabler-chevron-right class="w-3 h-3 text-gray-400" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    @else
                        <div class="px-4 py-3">
                            <div class="flex gap-3">
                                {{-- Icon --}}
                                <div class="flex-shrink-0 mt-0.5">
                                    @php
                                        $iconColorClass = match($activity['color']) {
                                            'success' => 'text-green-500',
                                            'info' => 'text-blue-500', 
                                            'danger' => 'text-red-500',
                                            default => 'text-gray-400',
                                        };
                                    @endphp
                                    <x-dynamic-component 
                                        :component="'tabler-' . str_replace('tabler-', '', $activity['icon'])"
                                        class="w-4 h-4 {{ $iconColorClass }}"
                                    />
                                </div>

                                {{-- Content --}}
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-900 dark:text-white leading-relaxed">
                                        {{ $activity['description'] }}
                                    </p>
                                    <div class="flex items-center justify-between mt-1">
                                        <span class="text-xs text-gray-600 dark:text-gray-400">
                                            {{ $activity['created_at']->diffForHumans() }}
                                        </span>
                                        @if($activity['subject_type'] && $activity['subject_id'])
                                            <span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                                                {{ $activity['subject_type'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @else
            {{-- Empty State --}}
            <div class="flex flex-col items-center justify-center h-32 px-4">
                <x-tabler-activity class="w-8 h-8 text-gray-400 mb-2" />
                <p class="text-sm text-gray-600 dark:text-gray-400 text-center">
                    No recent activity
                    @if($context['type'] !== 'dashboard')
                        for this {{ $context['type'] }}
                    @endif
                </p>
            </div>
        @endif
    </div>

    {{-- Footer Actions --}}
    <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
        @if($context['type'] !== 'dashboard')
            <a 
                href="{{ route('filament.member.pages.dashboard') }}" 
                class="inline-flex items-center gap-2 text-xs text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300"
            >
                <x-tabler-arrow-left class="w-3 h-3" />
                View all activity
            </a>
        @else
            <div class="flex items-center justify-between">
                <span class="text-xs text-gray-600 dark:text-gray-400">
                    {{ $activities->count() }} recent items
                </span>
                @if($activities->count() === 15)
                    <span class="text-xs text-primary-600 dark:text-primary-400">
                        More available
                    </span>
                @endif
            </div>
        @endif
    </div>
    </aside>
</div>