<?php

namespace CorvMC\Moderation\Actions\Reports;

use CorvMC\Moderation\Services\ReportService;

/**
 * @deprecated Use ReportService::resolveReport() instead
 * This action is maintained for backward compatibility only.
 * New code should use the ReportService directly.
 */
class ResolveReport
{
    /**
     * @deprecated Use ReportService::resolveReport() instead
     */
    public function handle(...$args)
    {
        return app(ReportService::class)->resolveReport(...$args);
    }
}
