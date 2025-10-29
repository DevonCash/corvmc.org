@php
    $actions = $this->getQuickActions();
@endphp

<x-filament-widgets::widget class="fi-quick-actions-widget">
    <div class="flex flex-wrap gap-3">
        @foreach ($actions as $action)
            <a href="{{ $action['url'] }}"
                class="flex flex-col items-center justify-center gap-3 p-6 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 hover:shadow-sm transition-all duration-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 flex-1 min-w-[150px] max-w-[200px]">
                @php
                    $colorClasses = match ($action['color']) {
                        'primary' => 'text-primary-500',
                        'success' => 'text-green-500',
                        'warning' => 'text-amber-500',
                        'danger' => 'text-red-500',
                        'info' => 'text-blue-500',
                        default => 'text-gray-500',
                    };
                @endphp
                <x-dynamic-component :component="'tabler-' . str_replace('tabler-', '', $action['icon'])" class="w-12 h-12 {{ $colorClasses }}" />
                <h3 class="text-sm font-medium text-gray-900 dark:text-white text-center">
                    {{ $action['label'] }}
                </h3>
            </a>
        @endforeach
    </div>

</x-filament-widgets::widget>
