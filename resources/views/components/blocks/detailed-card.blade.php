@props(['name', 'icon' => null, 'iconColor' => 'bg-amber-500', 'description' => null, 'details' => [], 'activitiesLabel' => null, 'activities' => [], 'tip' => null])

<div class="card shadow-xl border-2">
    <div class="card-body">
        <div class="flex items-center gap-3 mb-4">
            @if($icon)
                <div class="w-12 h-12 {{ $iconColor }} rounded-full flex items-center justify-center">
                    <x-icon :name="$icon" class="size-6 text-white" />
                </div>
            @endif
            <h3 class="card-title text-2xl">{{ $name }}</h3>
        </div>

        @if($description)
            <p class="text-lg mb-4">{{ $description }}</p>
        @endif

        @if(count($details))
            <div class="grid grid-cols-2 gap-4 mb-4">
                @foreach($details as $detail)
                    <div>
                        <h5 class="font-bold text-amber-700">{{ $detail['label'] }}</h5>
                        <p class="text-sm">{!! nl2br(e($detail['value'])) !!}</p>
                    </div>
                @endforeach
            </div>
        @endif

        @if($activitiesLabel && count($activities))
            <div class="mb-4">
                <h5 class="font-bold text-amber-700 mb-2">{{ $activitiesLabel }}</h5>
                <ul class="space-y-1 text-sm list-disc list-inside">
                    @foreach($activities as $activity)
                        <li>{{ $activity['text'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($tip)
            <div class="alert alert-warning">
                <x-icon name="tabler-info-circle" class="size-4" />
                <span class="text-sm">{{ $tip }}</span>
            </div>
        @endif
    </div>
</div>
