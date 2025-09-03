<div x-data="{ 
    init() {
        this.$wire.set('pageUrl', window.location.href);
    }
}">
    {{ $this->feedbackAction }}
    
    <x-filament-actions::modals />
</div>