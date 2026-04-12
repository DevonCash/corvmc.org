<?php

namespace CorvMC\Moderation\Actions\Reports;

use CorvMC\Moderation\Services\ReportService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use ReportService::getReportsNeedingAttention() instead
 * This action is maintained for backward compatibility only.
 * New code should use the ReportService directly.
 */
class GetReportsNeedingAttention
{
    use AsAction;

    /**
     * @deprecated Use ReportService::getReportsNeedingAttention() instead
     */
    public function handle(...$args)
    {
        return app(ReportService::class)->getReportsNeedingAttention(...$args);
    }
}
