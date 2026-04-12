<?php

namespace CorvMC\Moderation\Actions\Reports;

use CorvMC\Moderation\Services\ReportService;

/**
 * @deprecated Use ReportService::bulkResolveReports() instead
 * This action is maintained for backward compatibility only.
 * New code should use the ReportService directly.
 */
class BulkResolveReports
{
    /**
     * @deprecated Use ReportService::bulkResolveReports() instead
     */
    public function handle(...$args)
    {
        return app(ReportService::class)->bulkResolveReports(...$args);
    }
}
