<div x-data="{
    init() {
        this.$wire.set('pageUrl', window.location.href);
    }
}">
    @if (app()->environment() !== 'production')
        <div class="bg-warning text-warning-content">
            <div class="container mx-auto px-4 py-3">
                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <div class="flex items-center gap-3">
                        <x-tabler-alert-triangle class="size-5 flex-shrink-0" />
                        <div>
                            <span class="font-semibold">Development Site</span>
                            <span class="hidden sm:inline"> – This site is under development. Information may not be accurate and features may not work as expected.</span>
                            <span class="sm:hidden"> – Under development, info may not be accurate</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        {{ $this->feedbackAction }}
                    </div>
                </div>
            </div>
        </div>

        <x-filament-actions::modals />
    @endif
</div>
