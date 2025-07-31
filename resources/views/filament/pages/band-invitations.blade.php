<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center mb-6">
                <x-heroicon-s-envelope class="w-6 h-6 text-blue-600 mr-3" />
                <h2 class="text-xl font-semibold text-gray-900">Pending Band Invitations</h2>
            </div>
            
            <p class="text-gray-600 mb-6">
                You have been invited to join the following bands. Review each invitation and choose to accept or decline.
            </p>

            <div class="space-y-4">
                @foreach($this->getPendingInvitations() as $band)
                    @php
                        $invitation = $band->members->first();
                        $role = $invitation->pivot->role ?? 'member';
                        $position = $invitation->pivot->position;
                        $invitedAt = $invitation->pivot->invited_at;
                    @endphp
                    
                    <div class="border border-gray-200 rounded-lg p-6 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start space-x-4">
                                <img src="{{ $band->avatar_url ?: 'https://ui-avatars.com/api/?name=' . urlencode($band->name) . '&color=7C3AED&background=F3E8FF&size=120' }}"
                                     alt="{{ $band->name }}"
                                     class="w-16 h-16 rounded-full object-cover flex-shrink-0">
                                
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-gray-900">{{ $band->name }}</h3>
                                    
                                    @if($band->hometown)
                                        <div class="flex items-center text-gray-500 text-sm mt-1">
                                            <x-heroicon-s-map-pin class="w-3 h-3 mr-1" />
                                            {{ $band->hometown }}
                                        </div>
                                    @endif
                                    
                                    <div class="mt-3 space-y-2">
                                        <div class="flex items-center">
                                            <span class="text-sm text-gray-600 mr-2">Role:</span>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $role === 'admin' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                                {{ ucfirst($role) }}
                                            </span>
                                        </div>
                                        
                                        @if($position)
                                            <div class="flex items-center">
                                                <span class="text-sm text-gray-600 mr-2">Position:</span>
                                                <span class="text-sm text-gray-900">{{ $position }}</span>
                                            </div>
                                        @endif
                                        
                                        <div class="flex items-center">
                                            <span class="text-sm text-gray-600 mr-2">Invited:</span>
                                            <span class="text-sm text-gray-500">{{ \Carbon\Carbon::parse($invitedAt)->diffForHumans() }}</span>
                                        </div>
                                    </div>
                                    
                                    @if($band->bio)
                                        <div class="mt-3 text-sm text-gray-600 line-clamp-2">
                                            {!! Str::limit(strip_tags($band->bio), 150) !!}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-col space-y-2 ml-4">
                                <button 
                                    wire:click="acceptInvitation({{ $band->id }})"
                                    class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 transition-colors">
                                    <x-heroicon-s-check class="w-4 h-4 mr-2" />
                                    Accept
                                </button>
                                
                                <button 
                                    wire:click="declineInvitation({{ $band->id }})"
                                    wire:confirm="Are you sure you want to decline this invitation?"
                                    class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-md hover:bg-gray-700 transition-colors">
                                    <x-heroicon-s-x-mark class="w-4 h-4 mr-2" />
                                    Decline
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
