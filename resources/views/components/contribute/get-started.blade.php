<!-- Get Started -->
<div class="text-center">
    <h2 class="text-3xl font-bold mb-4">Ready to Get Involved?</h2>
    <p class="text-lg mb-8 max-w-2xl mx-auto">
        Whether you have time to volunteer or want to make a financial contribution, every bit of support helps
        us build a stronger music community.
    </p>
    <div class="flex flex-col sm:flex-row gap-4 justify-center">
        <a href="{{ route('volunteer') }}" class="btn btn-primary btn-lg">
            <x-unicon name="tabler:heart-handshake" class="size-5 mr-2" />
            Start Volunteering
        </a>
        <a href="{{ route('support') }}" class="btn btn-secondary btn-lg">
            <x-unicon name="tabler:heart" class="size-5 mr-2" />
            Make a Donation
        </a>
        <a href="{{ route('contact') }}?topic=general" class="btn btn-outline btn-accent btn-lg">
            <x-unicon name="tabler:mail" class="size-5 mr-2" />
            Contact Us
        </a>
    </div>

    <!-- Contact info -->
    <div class="mt-8 p-4 bg-base-200 rounded-lg max-w-md mx-auto">
        <p class="text-sm opacity-70 mb-2">For donations and questions:</p>
        <a href="mailto:donations@corvallismusiccollective.org" class="text-primary font-semibold">
            donations@corvallismusiccollective.org
        </a>
    </div>
</div>