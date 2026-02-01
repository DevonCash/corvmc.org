<x-filament-panels::page>
    <div class="space-y-6">
        @include('filament.staff.partials.todays-operations', ['data' => $this->getTodaysOperationsData()])
        <div class="grid gap-6 lg:grid-cols-2">
            @include('filament.staff.partials.membership-health', ['data' => $this->getMembershipHealthData()])
            @include('filament.staff.partials.monthly-charges', ['data' => $this->getMonthlyChargesData()])
        </div>
        @include('filament.staff.partials.activity-feed', ['activities' => $this->getRecentActivities()])
    </div>
</x-filament-panels::page>
