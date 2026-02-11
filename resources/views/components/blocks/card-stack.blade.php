@props(['cards' => []])

<div class="space-y-6">
    @foreach($cards as $card)
        <div class="card bg-base-100 shadow-lg">
            <div class="card-body">
                <div class="flex items-center gap-3 mb-3">
                    @if($card['icon'] ?? null)
                        <x-icon :name="$card['icon']" class="size-6 {{ $card['icon_color'] ?? 'text-primary' }}" />
                    @endif
                    <h4 class="card-title">{{ $card['name'] }}</h4>
                </div>

                @if($card['description'] ?? null)
                    <p class="text-sm mb-3">{{ $card['description'] }}</p>
                @endif

                @if($card['badge'] ?? null)
                    <div class="badge badge-primary badge-outline">{{ $card['badge'] }}</div>
                @endif
            </div>
        </div>
    @endforeach
</div>
