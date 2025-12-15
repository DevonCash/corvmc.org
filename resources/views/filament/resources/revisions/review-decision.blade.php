@php
    $isApproved = $revision->status === \App\Models\Revision::STATUS_APPROVED;
    $isRejected = $revision->status === \App\Models\Revision::STATUS_REJECTED;
@endphp

<div class="rounded-lg border-2 {{ $isApproved ? 'border-green-500 bg-green-50 dark:bg-green-950/20' : 'border-red-500 bg-red-50 dark:bg-red-950/20' }} p-4 mb-6">
    <div class="flex items-start gap-3">
        {{-- Icon --}}
        <div class="flex-shrink-0 mt-0.5">
            @if ($isApproved)
                <x-tabler-circle-check class="w-6 h-6 text-green-600 dark:text-green-400" />
            @else
                <x-tabler-circle-x class="w-6 h-6 text-red-600 dark:text-red-400" />
            @endif
        </div>

        {{-- Content --}}
        <div class="flex-1 min-w-0">
            {{-- Status Heading --}}
            <h3 class="font-semibold text-base {{ $isApproved ? 'text-green-900 dark:text-green-100' : 'text-red-900 dark:text-red-100' }} mb-1">
                @if ($isApproved)
                    Revision Approved
                    @if ($revision->auto_approved)
                        <span class="text-xs font-normal opacity-75">(Auto-approved by trust system)</span>
                    @endif
                @else
                    Revision Rejected
                @endif
            </h3>

            {{-- Reviewer Info --}}
            <div class="text-sm {{ $isApproved ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300' }} space-y-1">
                @if ($revision->reviewedBy)
                    <p>
                        <span class="font-medium">Reviewed by:</span>
                        {{ $revision->reviewedBy->name }}
                    </p>
                @endif

                @if ($revision->reviewed_at)
                    <p>
                        <span class="font-medium">Reviewed:</span>
                        {{ $revision->reviewed_at->format('M j, Y g:i A') }}
                        <span class="opacity-75">({{ $revision->reviewed_at->diffForHumans() }})</span>
                    </p>
                @endif

                @if ($revision->review_reason)
                    <div class="mt-3 pt-3 border-t {{ $isApproved ? 'border-green-200 dark:border-green-800' : 'border-red-200 dark:border-red-800' }}">
                        <p class="font-medium mb-1">{{ $isApproved ? 'Approval Note:' : 'Rejection Reason:' }}</p>
                        <p class="italic">{{ $revision->review_reason }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
