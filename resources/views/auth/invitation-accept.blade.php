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
                    
                    <form method="POST" action="{{ route('invitation.accept.store', $token) }}" class="space-y-4">
                        @csrf
                        
                        <!-- Name -->
                        <div class="form-control">
                            <label class="label" for="name">
                                <span class="label-text">Full Name</span>
                            </label>
                            <input 
                                type="text" 
                                id="name" 
                                name="name" 
                                value="{{ old('name') }}" 
                                class="input input-bordered @error('name') input-error @enderror" 
                                required 
                                autofocus 
                                placeholder="Enter your full name"
                            >
                            @error('name')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <!-- Password -->
                        <div class="form-control">
                            <label class="label" for="password">
                                <span class="label-text">Password</span>
                            </label>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                class="input input-bordered @error('password') input-error @enderror" 
                                required
                                placeholder="Create a secure password"
                            >
                            @error('password')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <!-- Confirm Password -->
                        <div class="form-control">
                            <label class="label" for="password_confirmation">
                                <span class="label-text">Confirm Password</span>
                            </label>
                            <input 
                                type="password" 
                                id="password_confirmation" 
                                name="password_confirmation" 
                                class="input input-bordered" 
                                required
                                placeholder="Confirm your password"
                            >
                        </div>

                        <!-- Token Error -->
                        @error('token')
                            <div class="alert alert-error">
                                <x-unicon name="tabler:alert-circle" class="w-5 h-5" />
                                <span>{{ $message }}</span>
                            </div>
                        @enderror

                        <!-- Submit -->
                        <div class="form-control mt-6">
                            <button type="submit" class="btn btn-primary">
                                <x-unicon name="tabler:user-check" class="w-5 h-5" />
                                Complete Registration
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Additional Info -->
            <div class="text-center mt-8 text-sm opacity-70">
                <p>By completing registration, you agree to be part of the Corvallis Music Collective community.</p>
                <p class="mt-2">Need help? <a href="{{ route('contact') }}?topic=membership" class="link link-primary">Contact us</a></p>
            </div>
        </div>
    </div>
</x-public.layout>