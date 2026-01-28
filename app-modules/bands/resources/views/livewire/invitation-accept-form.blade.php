<div>
    <form wire:submit="submit" class="space-y-4">
        {{ $this->form }}

        <!-- Token Error Display -->
        @if (session()->has('error'))
            <div class="alert alert-error">
                <x-icon name="tabler-alert-circle" class="w-5 h-5" />
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <!-- Submit -->
        <div class="form-control mt-6">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove class='flex gap-2'>
                    <x-icon name="tabler-user-check" class="w-5 h-5" />
                    Complete Registration
                </span>
                <span wire:loading class='flex gap-2'>
                    <span class="loading loading-spinner loading-sm"></span>
                    <span>Processing...</span>
                </span>
            </button>
        </div>
    </form>
</div>
