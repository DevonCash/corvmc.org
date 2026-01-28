@php
    $record = $this->getRecord();
    $reportable = $record->reportable;
@endphp

<div class="space-y-4">
    @if($reportable)
        <div class="border rounded-lg p-4 bg-gray-50 dark:bg-gray-800">
            <div class="flex items-center justify-between mb-3">
                <h4 class="font-medium text-gray-900 dark:text-gray-100">
                    @switch(get_class($reportable))
                        @case('App\Models\Production')
                            Production: {{ $reportable->title }}
                            @break
                        @case('App\Models\MemberProfile')
                            Member Profile: {{ $reportable->user->name }}
                            @break
                        @case('CorvMC\Bands\Models\Band')
                            Band Profile: {{ $reportable->name }}
                            @break
                        @default
                            {{ class_basename($reportable) }}: {{ $reportable->title ?? $reportable->name ?? '#' . $reportable->id }}
                    @endswitch
                </h4>

                <a href="{{ $this->getContentUrl($reportable) }}"
                   target="_blank"
                   class="text-sm text-primary-600 hover:text-primary-500">
                    View Full Content →
                </a>
            </div>

            @switch(get_class($reportable))
                @case('App\Models\Production')
                    <div class="text-sm text-gray-600 dark:text-gray-400 space-y-2">
                        @if($reportable->description)
                            <p><strong>Description:</strong> {{ Str::limit($reportable->description, 200) }}</p>
                        @endif
                        @if($reportable->start_datetime)
                            <p><strong>Date:</strong> {{ $reportable->start_datetime->format('M j, Y g:i A') }}</p>
                        @endif
                        @if($reportable->location)
                            <p><strong>Location:</strong> {{ $reportable->venue_name }}</p>
                        @endif
                    </div>
                    @break

                @case('App\Models\MemberProfile')
                    <div class="text-sm text-gray-600 dark:text-gray-400 space-y-2">
                        @if($reportable->bio)
                            <p><strong>Bio:</strong> {{ Str::limit($reportable->bio, 200) }}</p>
                        @endif
                        @if($reportable->hometown)
                            <p><strong>Location:</strong> {{ $reportable->hometown }}</p>
                        @endif
                        @if($reportable->tagsWithType('skill')->count() > 0)
                            <p><strong>Skills:</strong> {{ $reportable->tagsWithType('skill')->pluck('name')->join(', ') }}</p>
                        @endif
                    </div>
                    @break

                @case('CorvMC\Bands\Models\Band')
                    <div class="text-sm text-gray-600 dark:text-gray-400 space-y-2">
                        @if($reportable->bio)
                            <p><strong>Bio:</strong> {{ Str::limit($reportable->bio, 200) }}</p>
                        @endif
                        @if($reportable->hometown)
                            <p><strong>Location:</strong> {{ $reportable->hometown }}</p>
                        @endif
                        @if($reportable->tagsWithType('genre')->count() > 0)
                            <p><strong>Genres:</strong> {{ $reportable->tagsWithType('genre')->pluck('name')->join(', ') }}</p>
                        @endif
                    </div>
                    @break
            @endswitch
        </div>
    @else
        <div class="text-sm text-gray-500 italic">
            The reported content has been deleted or is no longer available.
        </div>
    @endif
</div>
