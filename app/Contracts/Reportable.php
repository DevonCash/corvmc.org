<?php

namespace App\Contracts;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 */
interface Reportable
{
    /**
     * Get all reports for this content.
     */
    public function reports(): MorphMany;

    /**
     * Check if this content has reached the report threshold.
     */
    public function hasReachedReportThreshold(): bool;

    /**
     * Check if this content should be auto-hidden when threshold is reached.
     */
    public function shouldAutoHide(): bool;

    /**
     * Get the human-readable type name for this reportable content.
     */
    public function getReportableType(): string;

    /**
     * Get the user who created/owns this content.
     */
    public function getContentCreator(): ?User;

    /**
     * Get the trust content type for this reportable.
     */
    public function getTrustContentType(): string;
}
