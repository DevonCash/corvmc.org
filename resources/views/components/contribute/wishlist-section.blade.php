<div class="mb-16">
    <div class="text-center mb-12">
        <h2 class="text-4xl font-bold mb-4">Donation Wishlist</h2>
        <p class="text-lg opacity-70">Looking to help out? Here's some of what we're looking for:</p>
    </div>

    <!-- High Priority Immediate Needs -->
    <div class="mb-12">
        <h3 class="text-2xl font-bold mb-6 flex items-center gap-3">
            <x-icon name="tabler-flame" class="size-7 text-error" />
            Premises Improvements
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <x-contribute.wishlist-item
                icon="tabler-window"
                title="Professional Window Signage"
                price="$200-600"
                description="Vinyl lettering/graphics for storefront visibility"
                color="primary"
                :priority="true"
            />

            <x-contribute.wishlist-item
                icon="tabler-key"
                title="Keyless Entry System"
                price="$150-500"
                description="Code-controlled, remote programming preferred"
                color="secondary"
                :priority="true"
            />

            <x-contribute.wishlist-item
                icon="tabler-video"
                title="Security Camera System"
                price="$300-800"
                description="Indoor/outdoor coverage with remote monitoring"
                color="accent"
                :priority="true"
            />

            <x-contribute.wishlist-item
                icon="tabler-device-mobile"
                title="Used iPhone with NFC"
                price="$200-400"
                description="For tap-to-pay via Zeffy (huge operational upgrade!)"
                color="warning"
                :priority="true"
            />

            <x-contribute.wishlist-item
                icon="tabler-bulb"
                title="Modern LED Stage Lighting"
                price="$300-800"
                description="Smart/home automation compatible"
                color="info"
                :priority="true"
            />

            <x-contribute.wishlist-item
                icon="tabler-printer"
                title="Epson EcoTank Printer"
                price="$300-400"
                description="ET-3850 for high-volume poster printing"
                color="success"
                :priority="true"
            />
        </div>
    </div>

    <!-- Audio Equipment -->
    <div class="mb-12">
        <h3 class="text-2xl font-bold mb-6 flex items-center gap-3">
            <x-icon name="tabler-music" class="size-7 text-primary" />
            Sound & Audio Equipment
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <x-contribute.wishlist-item
                icon="tabler-device-audio-tape"
                title="Digital Mixer"
                price="$400-800"
                description="Replace loaned MR18 - Behringer XR18 or Midas MR18"
                color="primary"
            />

            <x-contribute.wishlist-item
                icon="tabler-microphone"
                title="Basic Microphones"
                price="$50-100"
                description="SM57/58 style mics, used condition OK"
                color="secondary"
            />

            <x-contribute.wishlist-item
                icon="tabler-vinyl"
                title="Used Turntable"
                price="$100-300"
                description="Direct drive preferred for obsolete media library"
                color="accent"
            />
        </div>
    </div>

    <!-- Facility & Infrastructure -->
    <div>
        <h3 class="text-2xl font-bold mb-6 flex items-center gap-3">
            <x-icon name="tabler-building" class="size-7 text-warning" />
            Facility Improvements
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <x-contribute.wishlist-item
                icon="tabler-paint"
                title="Stage Paint & Supplies"
                price="$50-100"
                description="Black or dark gray paint for stage area"
                color="primary"
            />

            <x-contribute.wishlist-item
                icon="tabler-armchair"
                title="Counter Height Stools"
                price="$100-400"
                description="2-4 stools for retail/merchandise area"
                color="secondary"
            />

            <x-contribute.wishlist-item
                icon="tabler-tools"
                title="Workshop Setup"
                price="$200-600"
                description="Workbench, tool organization, soldering station"
                color="accent"
            />
        </div>
    </div>
</div>
