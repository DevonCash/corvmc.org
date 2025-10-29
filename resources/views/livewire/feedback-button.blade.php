<div x-data="{
    init() {
        this.$wire.set('pageUrl', window.location.href);
    }
}" class="feedback-button-wrapper">
    {{ $this->feedbackAction }}
    <x-filament-actions::modals />
</div>
