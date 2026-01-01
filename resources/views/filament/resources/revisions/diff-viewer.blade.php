<div class="space-y-4">
    @if (empty($changes))
        <div class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">
            No changes detected
        </div>
    @else
        <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">
            <span class="font-medium">{{ $modelType }}:</span> {{ $modelTitle }}
        </div>

        @foreach ($changes as $field => $change)
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                {{-- Field Name Header --}}
                <div class="bg-gray-50 dark:bg-gray-800 px-4 py-2 border-b border-gray-200 dark:border-gray-700">
                    <span class="font-semibold text-sm text-gray-900 dark:text-white">
                        {{ Str::headline($field) }}
                    </span>
                </div>

                <div class="grid grid-cols-2 divide-x divide-gray-200 dark:divide-gray-700">
                    {{-- Original Value --}}
                    <div class="p-4 bg-red-50 dark:bg-red-950/20">
                        <div class="text-xs font-semibold text-red-700 dark:text-red-400 mb-2 flex items-center gap-1">
                            <x-tabler-minus class="w-3 h-3" />
                            Original
                        </div>
                        <div class="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-wrap break-words">
                            @if (is_null($change['from']))
                                <span class="italic text-gray-400 dark:text-gray-500">(empty)</span>
                            @elseif (is_bool($change['from']))
                                <span class="font-mono">{{ $change['from'] ? 'true' : 'false' }}</span>
                            @elseif (is_array($change['from']))
                                <pre class="text-xs bg-white dark:bg-gray-900 p-2 rounded border border-gray-200 dark:border-gray-700 overflow-x-auto">{{ json_encode($change['from'], JSON_PRETTY_PRINT) }}</pre>
                            @else
                                {{ $change['from'] }}
                            @endif
                        </div>
                    </div>

                    {{-- New Value --}}
                    <div class="p-4 bg-green-50 dark:bg-green-950/20">
                        <div class="text-xs font-semibold text-green-700 dark:text-green-400 mb-2 flex items-center gap-1">
                            <x-tabler-plus class="w-3 h-3" />
                            Proposed
                        </div>
                        <div class="text-sm text-gray-900 dark:text-gray-100 whitespace-pre-wrap break-words">
                            @if (is_null($change['to']))
                                <span class="italic text-gray-400 dark:text-gray-500">(empty)</span>
                            @elseif (is_bool($change['to']))
                                <span class="font-mono">{{ $change['to'] ? 'true' : 'false' }}</span>
                            @elseif (is_array($change['to']))
                                <pre class="text-xs bg-white dark:bg-gray-900 p-2 rounded border border-gray-200 dark:border-gray-700 overflow-x-auto">{{ json_encode($change['to'], JSON_PRETTY_PRINT) }}</pre>
                            @else
                                {{ $change['to'] }}
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Summary Footer --}}
        <div class="text-xs text-gray-500 dark:text-gray-400 text-center pt-2 border-t border-gray-200 dark:border-gray-700">
            {{ count($changes) }} {{ Str::plural('field', count($changes)) }} modified
        </div>
    @endif
</div>
