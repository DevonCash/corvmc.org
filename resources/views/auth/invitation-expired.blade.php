<x-public.layout title="Invitation Expired - Corvallis Music Collective">
    <div class="container mx-auto px-4 py-12">
        <div class="max-w-md mx-auto text-center">
            <!-- Header -->
            <div class="mb-8">
                <x-logo class="h-16 mx-auto mb-4" />
                <h1 class="text-2xl font-bold text-error">Invitation Expired</h1>
                <p class="text-base-content/70 mt-2">This invitation link is no longer valid</p>
            </div>

            <!-- Error Card -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <div class="text-error mb-4">
                        <x-unicon name="tabler:clock-x" class="w-16 h-16 mx-auto" />
                    </div>
                    
                    <h2 class="card-title justify-center text-lg mb-4">Sorry, this invitation has expired</h2>
                    
                    <div class="text-sm text-base-content/70 space-y-2 mb-6">
                        <p>Invitations are valid for 7 days from when they were sent.</p>
                        <p>This link may have expired or already been used.</p>
                    </div>

                    <div class="card-actions justify-center">
                        <a href="{{ route('contact') }}?topic=membership" class="btn btn-primary">
                            <x-unicon name="tabler:mail" class="w-5 h-5" />
                            Contact Us
                        </a>
                        <a href="{{ route('home') }}" class="btn btn-ghost">
                            <x-unicon name="tabler:home" class="w-5 h-5" />
                            Go Home
                        </a>
                    </div>
                </div>
            </div>

            <!-- Help Text -->
            <div class="mt-8 text-sm opacity-70">
                <p>If you need a new invitation, please contact us and we'll send you a fresh link.</p>
                <p class="mt-2">Already have an account? <a href="/member/login" class="link link-primary">Login here</a></p>
            </div>
        </div>
    </div>
</x-public.layout>