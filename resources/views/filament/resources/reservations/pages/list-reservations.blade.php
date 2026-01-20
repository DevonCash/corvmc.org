<x-filament-panels::page>
    {{-- Practice Space Information Section --}}
    <div class="text-sm text-gray-600 dark:text-gray-400 -mt-4 space-y-2">
        <p>
            Our practice space is available for $15/hour between 9 AM and 10 PM daily. Click "Reserve Space"
            above to book your session—you can reserve anywhere from 1 to 8 hours at a time. At least one day's notice
            is required, and payment is due at the reservation start time via cash in person, or via card online.
        </p>
        <p>
            If you have specific needs for equipment or space, please make a note in the reservation form.
        </p>
    </div>

    {{-- Free Hours Widget --}}
    @livewire(\App\Filament\Resources\Reservations\Widgets\FreeHoursWidget::class)

    {{-- Tabs --}}
    {{ $this->content }}

    {{-- Auto-open slide-over from email link --}}
    @if (request()->has('view'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const recordId = '{{ request()->get('view') }}';

                // Wait for Livewire to be ready
                if (typeof Livewire !== 'undefined') {
                    // Find the view button for the specific record and click it
                    setTimeout(() => {
                        const viewButton = document.querySelector(`[wire\\:click*="mountTableAction('view', '${recordId}')"]`);
                        if (viewButton) {
                            viewButton.click();
                        }
                    }, 500);
                }
            });
        </script>
    @endif
</x-filament-panels::page>
