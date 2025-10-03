<div>
    <form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-6 flex justify-center">
            <button type="submit" class="btn btn-primary btn-lg">
                <x-tabler-send class="size-5" />
                Send Message
            </button>
        </div>
    </form>

    <x-filament-actions::modals />
</div>
