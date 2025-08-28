<x-public.layout title="Accept Invitation - Corvallis Music Collective">
    <div class="container mx-auto px-4 py-12">
        <div class="max-w-md mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <x-logo class="h-16 mx-auto mb-4" />
                <h1 class="text-2xl font-bold text-primary">Welcome to CMC!</h1>
                <p class="text-base-content/70 mt-2">Complete your account setup to join the community</p>
            </div>

            <!-- Invitation Info -->
            <div class="card bg-base-200 mb-6">
                <div class="card-body p-4">
                    <h2 class="card-title text-lg">You're Invited!</h2>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="opacity-70">Email:</span>
                            <span class="font-medium">{{ $email }}</span>
                        </div>
                        @if(!empty($roles))
                        <div class="flex justify-between">
                            <span class="opacity-70">Role(s):</span>
                            <span class="font-medium">{{ implode(', ', $roles) }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Registration Form -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <h2 class="card-title text-xl mb-4">Complete Your Profile</h2>
                    
                    @livewire('invitation-accept-form', ['token' => $token])
                </div>
            </div>

            <!-- Additional Info -->
            <div class="text-center mt-8 text-sm opacity-70">
                <p>By completing registration, you agree to be part of the Corvallis Music Collective community and accept our <a href="{{ route('privacy-policy') }}" class="link link-primary">Privacy Policy</a>.</p>
                <p class="mt-2">Need help? <a href="{{ route('contact') }}?topic=membership" class="link link-primary">Contact us</a></p>
            </div>
        </div>
    </div>
</x-public.layout>