@php
    $actions = $this->getQuickActions();
@endphp

<x-filament-widgets::widget class="fi-quick-actions-widget">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach ($actions as $action)
            <a href="{{ $action['url'] }}"
                class="flex flex-col p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 hover:shadow-sm transition-all duration-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750">
                <div class="flex items-start gap-3 mb-2">
                    <div class="flex-shrink-0">
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
                        <x-dynamic-component :component="'tabler-' . str_replace('tabler-', '', $action['icon'])" class="w-5 h-5 {{ $colorClasses }}" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white truncate">
                            {{ $action['label'] }}
                        </h3>
                    </div>
                    <x-tabler-chevron-right class="w-4 h-4 text-gray-400 flex-shrink-0" />
                </div>

                <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                    {{ $action['description'] }}
                </p>
            </a>
        @endforeach
    </div>

</x-filament-widgets::widget>
