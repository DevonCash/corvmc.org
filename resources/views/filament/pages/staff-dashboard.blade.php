<x-filament-panels::page>
    <div class="space-y-6">
        @include('filament.staff.partials.todays-operations', ['data' => $this->getTodaysOperationsData()])
        @include('filament.staff.partials.membership-health', ['data' => $this->getMembershipHealthData()])
        @include('filament.staff.partials.activity-feed', ['activities' => $this->getRecentActivities()])
    </div>
</x-filament-panels::page>
