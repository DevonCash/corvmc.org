<x-public.layout title="Bylaws | Corvallis Music Collective">
    <!-- Hero Section -->
    <div class="hero min-h-96 bg-accent/20">
        <div class="hero-content text-center">
            <div class="max-w-2xl">
                <h1 class="text-5xl font-bold">Organization Bylaws</h1>
                <p class="py-6 text-lg">
                    Governing documents of the Corvallis Music Collective
                </p>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-16 max-w-4xl">
        <!-- Bylaws Content -->
        <article class="prose prose-lg max-w-none">
            {!! str($bylaws->content)->markdown() !!}
        </article>

        @if($bylaws->last_updated_at)
            <div class="mt-12 pt-8 border-t border-base-300">
                <p class="text-sm text-base-content/60">
                    Last updated: {{ \Carbon\Carbon::parse($bylaws->last_updated_at)->format('F j, Y') }}
                    @if($bylaws->last_updated_by)
                        @php
                            $updater = \App\Models\User::find($bylaws->last_updated_by);
                        @endphp
                        @if($updater)
                            by {{ $updater->name }}
                        @endif
                    @endif
                </p>
            </div>
        @endif

        <!-- Actions -->
        <div class="mt-8 flex gap-4">
            <a href="{{ route('about') }}" class="btn btn-outline">
                ← Back to About
            </a>
            <button onclick="window.print()" class="btn btn-outline">
                Print Bylaws
            </button>
        </div>
    </div>
</x-public.layout>
