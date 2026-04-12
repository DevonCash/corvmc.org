<?php

namespace CorvMC\Moderation\Actions\Reports;

use CorvMC\Moderation\Services\ReportService;

/**
 * @deprecated Use ReportService::submitReport() instead
 * This action is maintained for backward compatibility only.
 * New code should use the ReportService directly.
 */
class SubmitReport
{
    /**
     * @deprecated Use ReportService::submitReport() instead
     */
    public function handle(...$args)
    {
        return app(ReportService::class)->submitReport(...$args);
    }
}
