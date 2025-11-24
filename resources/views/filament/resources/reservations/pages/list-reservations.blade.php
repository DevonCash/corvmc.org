<x-filament-panels::page>
    {{-- Practice Space Information Section --}}
    <div class="text-sm text-gray-600 dark:text-gray-400 -mt-4 space-y-2">
        <p>
            Our practice space is available for $15/hour between 9 AM and 10 PM daily. Click "Reserve Space"
            above to book your session—you can reserve anywhere from 1 to 8 hours at a time. At least one day's notice
            is required, and payment is due at
            the reservation start time via cash in person, or via card online.
        </p>
        <p>
            If you have specific needs for equipment or space, please make a note in the reservation form.
        </p>
    </div>

    {{-- Free Hours Widget --}}
    @livewire(\App\Filament\Resources\Reservations\Widgets\FreeHoursWidget::class)

    {{-- Tabs --}}
    {{ $this->content }}
</x-filament-panels::page>
