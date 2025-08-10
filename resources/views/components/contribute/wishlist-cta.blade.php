<!-- CTA for Non-Wishlist Items -->
<div class="bg-accent/10 rounded-lg p-8 text-center mb-16">
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center justify-center gap-3 mb-4">
            <x-unicon name="tabler:bulb" class="size-8 text-accent" />
            <h3 class="text-2xl font-bold">Don't See What You Have?</h3>
        </div>
        <p class="text-lg mb-6">
            This wishlist isn't exhaustive! Have something else that might help our music community?
            We're always open to creative contributions and unexpected donations.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('contact') }}?topic=donation" class="btn btn-accent btn-lg">
                <x-unicon name="tabler:mail" class="size-5 mr-2" />
                Show Us What You've Got!
            </a>
            <a href="mailto:donations@corvallismusiccollective.org" class="btn btn-outline btn-accent btn-lg">
                <x-unicon name="tabler:send" class="size-5 mr-2" />
                Email Directly
            </a>
        </div>
        <p class="text-sm opacity-70 mt-4">
            Every donation, big or small, helps us build a stronger music community!
        </p>
    </div>
</div>
