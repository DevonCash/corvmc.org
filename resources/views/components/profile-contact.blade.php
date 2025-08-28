@props(['contact', 'profile' => null])

@can('view contact', $profile)
    @php $contact = $profile->contact; @endphp
    <div>
        <h3 class="font-bold text-base-content mb-3 uppercase tracking-wide text-sm border-b border-base-300 pb-1">
            Contact
        </h3>
        <div class="space-y-2 text-sm">
            @if ($contact->email)
                <a href="mailto:{{ $contact->email }}" class="flex items-center text-primary hover:text-primary-focus">
                    <x-tabler-mail class="w-4 h-4 mr-2" />
                    <span>{{ $contact->email }}</span>
                </a>
            @endif
            @if ($contact->phone)
                <a href="tel:{{ $contact->phone }}" class="flex items-center text-primary hover:text-primary-focus">
                    <x-tabler-phone class="w-4 h-4 mr-2" />
                    <span>{{ $contact->phone }}</span>
                </a>
            @endif
            @if (isset($contact->address) && $contact->address != '')
                <div class="flex items-start text-base-content/70">
                    <x-tabler-map-pin class="w-4 h-4 mr-2 mt-0.5 flex-shrink-0" />
                    <span>{{ $contact->address }}</span>
                </div>
            @endif
        </div>
    </div>
@endcan
