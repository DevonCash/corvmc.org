<?php

namespace CorvMC\Moderation\Actions\Reports;

use CorvMC\Moderation\Services\ReportService;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * @deprecated Use ReportService::bulkResolveReports() instead
 * This action is maintained for backward compatibility only.
 * New code should use the ReportService directly.
 */
class BulkResolveReports
{
    use AsAction;

    /**
     * @deprecated Use ReportService::bulkResolveReports() instead
     */
    public function handle(...$args)
    {
        return app(ReportService::class)->bulkResolveReports(...$args);
    }
}
