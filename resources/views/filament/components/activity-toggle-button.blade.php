{{-- Activity Toggle Button for Header --}}
<div x-on:click="$dispatch('toggle-activity-sidebar')" class="fi-modal-trigger">
    <button 
        class="fi-icon-btn fi-size-md fi-topbar-database-notifications-btn" 
        title="Open activity sidebar" 
        aria-label="Open activity sidebar" 
        type="button"
    >
        <x-tabler-activity class="fi-icon fi-size-lg" />
    </button>
</div>