<!-- Contact CTA for In-Kind Donations -->
<div class="bg-primary/10 rounded-lg p-8 text-center mb-16">
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center justify-center gap-3 mb-4">
            <x-unicon name="tabler:question-mark" class="size-8 text-primary" />
            <h3 class="text-2xl font-bold">Have Something We Need?</h3>
        </div>
        <p class="text-lg mb-6">
            We'd love to hear from you! Contact us to discuss how your donation can help our community.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('contact') }}" class="btn btn-primary btn-lg">
                <x-unicon name="tabler:mail" class="size-5 mr-2" />
                Contact Us About Donations
            </a>
            <a href="mailto:donations@corvallismusiccollective.org" class="btn btn-outline btn-primary btn-lg">
                <x-unicon name="tabler:send" class="size-5 mr-2" />
                Email Directly
            </a>
        </div>
        <p class="text-sm opacity-70 mt-4">
            Questions about what we need or how to donate? We're here to help!
        </p>
    </div>
</div>
