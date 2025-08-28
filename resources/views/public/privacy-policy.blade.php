<x-public.layout title="Privacy Policy - Corvallis Music Collective">
    <div class="container mx-auto px-4 py-12">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-12">
                <h1 class="text-4xl font-bold text-primary mb-4">Privacy Policy</h1>
                <p class="text-lg text-base-content/70">Last updated: {{ date('F j, Y') }}</p>
            </div>

            <!-- Content -->
            <div class="prose prose-lg max-w-none">
                <div class="alert alert-info mb-8">
                    <div class="flex items-start gap-3">
                        <x-unicon name="tabler:info-circle" class="w-6 h-6 mt-1 flex-shrink-0" />
                        <div>
                            <h3 class="font-semibold text-lg mb-2">🚧 Under Development</h3>
                            <p class="mb-0">This privacy policy is currently being developed. Please check back soon for our complete privacy policy.</p>
                        </div>
                    </div>
                </div>

                <h2>Overview</h2>
                <p>The Corvallis Music Collective ("we," "our," or "us") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, and protect your personal information when you use our services.</p>

                <h2>Information We Collect</h2>
                <p><em>TODO: Detail what information is collected (member profiles, contact info, usage data, etc.)</em></p>

                <h2>How We Use Your Information</h2>
                <p><em>TODO: Explain how member data is used (community building, event notifications, practice space booking, etc.)</em></p>

                <h2>Information Sharing</h2>
                <p><em>TODO: Clarify what information is shared publicly vs. members-only vs. private</em></p>

                <h2>Data Security</h2>
                <p><em>TODO: Describe security measures for protecting member data</em></p>

                <h2>Member Rights</h2>
                <p><em>TODO: Outline member rights regarding their personal data (access, correction, deletion, etc.)</em></p>

                <h2>Contact Information</h2>
                <p>If you have questions about this Privacy Policy, please contact us:</p>
                <ul>
                    <li>Email: <a href="mailto:info@corvmc.org" class="link link-primary">info@corvmc.org</a></li>
                    <li>Contact Form: <a href="{{ route('contact') }}?topic=general" class="link link-primary">Contact Us</a></li>
                </ul>

                <div class="alert alert-warning mt-8">
                    <div class="flex items-start gap-3">
                        <x-unicon name="tabler:clock" class="w-6 h-6 mt-1 flex-shrink-0" />
                        <div>
                            <h4 class="font-semibold mb-2">Development Note</h4>
                            <p class="mb-0">This privacy policy needs to be completed before the site goes live. Consider consulting with a lawyer familiar with nonprofit privacy requirements.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-public.layout>