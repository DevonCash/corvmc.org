@php
    // Debug: Check what data is available
    // dd(get_defined_vars());
@endphp

<div
    x-data="{
        shouldShowCheckout: false,
        wireComponent: null,
        init() {
            // Find the Livewire component
            this.wireComponent = this.$el.closest('[wire\\\\:id]')?.__livewire;

            console.log('Submit button initialized');
            console.log('Livewire component:', this.wireComponent);

            this.checkConditions();

            // Watch for Livewire updates
            if (this.wireComponent) {
                Livewire.hook('commit', ({ component }) => {
                    if (component === this.wireComponent) {
                        console.log('Livewire updated, checking conditions');
                        this.checkConditions();
                    }
                });
            }

            // Also try Alpine's reactive approach
            this.$watch('wireComponent.data', () => {
                console.log('Data changed via Alpine watch');
                this.checkConditions();
            });
        },
        checkConditions() {
            if (!this.wireComponent) {
                console.log('No wireComponent found');
                this.shouldShowCheckout = false;
                return;
            }

            const cost = this.wireComponent.data?.cost || this.wireComponent.$wire?.data?.cost;
            const reservationDate = this.wireComponent.data?.reservation_date || this.wireComponent.$wire?.data?.reservation_date;
            const isRecurring = this.wireComponent.data?.is_recurring || this.wireComponent.$wire?.data?.is_recurring;

            console.log('Checking conditions:', { cost, reservationDate, isRecurring });

            // Show checkout if cost > 0 and reservation is within auto-confirm range
            if (!cost || cost <= 0) {
                console.log('No cost or cost <= 0');
                this.shouldShowCheckout = false;
                return;
            }

            if (isRecurring) {
                console.log('Is recurring');
                this.shouldShowCheckout = false;
                return;
            }

            if (!reservationDate) {
                console.log('No reservation date');
                this.shouldShowCheckout = false;
                return;
            }

            const resDate = new Date(reservationDate);
            const oneWeekFromNow = new Date();
            oneWeekFromNow.setDate(oneWeekFromNow.getDate() + 7);

            // Show checkout for reservations within the next week
            this.shouldShowCheckout = resDate <= oneWeekFromNow;
            console.log('Should show checkout:', this.shouldShowCheckout);
        }
    }"
>
    <x-filament::button
        type="submit"
        size="sm"
        x-show="shouldShowCheckout"
        x-transition
        color="primary"
        icon="tabler-credit-card"
    >
        Checkout with Stripe
    </x-filament::button>

    <x-filament::button
        type="submit"
        size="sm"
        x-show="!shouldShowCheckout"
        x-transition
    >
        Request Reservation
    </x-filament::button>
</div>
