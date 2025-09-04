@php
    $record = $this->getRecord();
    $activities = $record->activities()->with('causer')->latest()->get();
@endphp

<div class="space-y-4">
    @if($activities->count() > 0)
        <div class="flow-root">
            <ul role="list" class="-mb-8">
                @foreach($activities as $activity)
                    <li>
                        <div class="relative pb-8">
                            @if(!$loop->last)
                                <span class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span>
                            @endif
                            
                            <div class="relative flex space-x-3">
                                <div>
                                    @switch($activity->event)
                                        @case('created')
                                            <span class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center ring-8 ring-white dark:ring-gray-900">
                                                <x-heroicon-o-plus class="h-4 w-4 text-white" />
                                            </span>
                                            @break
                                        @case('updated')
                                            @php
                                                $isResolution = str_contains(strtolower($activity->description), 'upheld') || 
                                                               str_contains(strtolower($activity->description), 'dismissed') ||
                                                               str_contains(strtolower($activity->description), 'escalated');
                                            @endphp
                                            
                                            @if($isResolution)
                                                <span class="h-8 w-8 rounded-full bg-green-500 flex items-center justify-center ring-8 ring-white dark:ring-gray-900">
                                                    <x-heroicon-o-check class="h-4 w-4 text-white" />
                                                </span>
                                            @else
                                                <span class="h-8 w-8 rounded-full bg-yellow-500 flex items-center justify-center ring-8 ring-white dark:ring-gray-900">
                                                    <x-heroicon-o-pencil class="h-4 w-4 text-white" />
                                                </span>
                                            @endif
                                            @break
                                        @default
                                            <span class="h-8 w-8 rounded-full bg-gray-400 flex items-center justify-center ring-8 ring-white dark:ring-gray-900">
                                                <x-heroicon-o-document-text class="h-4 w-4 text-white" />
                                            </span>
                                    @endswitch
                                </div>
                                
                                <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                    <div>
                                        <p class="text-sm text-gray-900 dark:text-gray-100">
                                            {{ $activity->description }}
                                        </p>
                                        
                                        @if($activity->causer)
                                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                                by {{ $activity->causer->name }}
                                            </p>
                                        @endif
                                        
                                        @if($activity->properties->count() > 0)
                                            <div class="mt-2">
                                                @foreach($activity->properties as $key => $value)
                                                    @if($key === 'resolution_notes' && !empty($value))
                                                        <div class="text-xs text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 rounded px-2 py-1 mt-1">
                                                            <strong>Notes:</strong> {{ $value }}
                                                        </div>
                                                    @elseif($key === 'reason' && !empty($value))
                                                        <div class="text-xs text-gray-600 dark:text-gray-400">
                                                            <strong>Reason:</strong> {{ \App\Models\Report::REASONS[$value] ?? $value }}
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <div class="whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">
                                        <time datetime="{{ $activity->created_at->toISOString() }}">
                                            {{ $activity->created_at->diffForHumans() }}
                                        </time>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="text-sm text-gray-500 italic">
            No activity recorded for this report.
        </div>
    @endif
</div>