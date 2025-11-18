<?php

namespace App\Contracts;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
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
     * Each model must implement this to return the appropriate owner/creator relationship.
     */
    public function getContentCreator(): ?User;

    /**
     * Get the trust content type for this reportable.
     */
    public function getTrustContentType(): string;

    /**
     * Check if this content has been reported by a specific user.
     */
    public function hasBeenReportedBy($user): bool;
}
