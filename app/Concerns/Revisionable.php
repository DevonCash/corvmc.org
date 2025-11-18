<?php

namespace App\Concerns;

use App\Actions\Trust\DetermineApprovalWorkflow;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Revisionable Trait
 *
 * Adds revision system functionality to any model.
 * Models using this trait will have their updates go through
 * the revision approval process instead of being immediately applied.
 */
trait Revisionable
{
    /**
     * Determines if revisions are required for this model.
     * Can be overridden per model to customize behavior.
     */
    protected static bool $requiresRevisions = true;

    /**
     * Auto-approval behavior for this content type.
     * Override in models to customize behavior.
     * Options:
     * - 'trusted': Auto-approve based on trust system thresholds (default)
     * - 'personal': Auto-approve unless user is in poor standing (for personal content)
     * - 'untilReport': Auto-approve until content receives credible reports
     * - 'never': Always require manual approval (for organizational content)
     */

    /**
     * Fields that should bypass the revision system.
     * These fields will be updated immediately without approval.
     */
    protected array $revisionExemptFields = [
        'updated_at',
        'created_at',
        'deleted_at',
        'last_seen_at',
    ];

    /**
     * Boot the revisionable trait.
     */
    public static function bootRevisionable(): void
    {
        // Override the updating event to intercept changes
        static::updating(function ($model) {
            if ($model->shouldCreateRevision()) {
                return $model->interceptUpdate();
            }
        });
    }

    /**
     * Get all revisions for this model.
     */
    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable')->latest()->orderBy('id', 'desc');
    }

    /**
     * Get pending revisions for this model.
     */
    public function pendingRevisions(): MorphMany
    {
        return $this->revisions()->where('status', Revision::STATUS_PENDING);
    }

    /**
     * Check if this model has pending revisions.
     */
    public function hasPendingRevisions(): bool
    {
        return $this->pendingRevisions()->exists();
    }

    /**
     * Get the most recent revision.
     */
    public function latestRevision(): ?Revision
    {
        return $this->revisions()->first();
    }

    /**
     * Create a revision for the current changes.
     */
    public function createRevision(
        ?array $changes = null,
        ?User $submitter = null,
        ?string $reason = null,
        string $type = Revision::TYPE_UPDATE
    ): Revision {
        $changes = $changes ?? $this->getDirty();
        $submitter = $submitter ?? Auth::user();

        if (! $submitter) {
            throw new \InvalidArgumentException('Cannot create revision without a submitter user');
        }

        // Filter out exempt fields
        $changes = $this->filterRevisionExemptFields($changes);

        if (empty($changes)) {
            throw new \InvalidArgumentException('No revisionable changes to submit');
        }

        $revision = Revision::create([
            'revisionable_type' => static::class,
            'revisionable_id' => $this->getKey(),
            'original_data' => $this->getOriginal(),
            'proposed_changes' => $changes,
            'submitted_by_id' => $submitter->id,
            'submission_reason' => $reason,
            'revision_type' => $type,
            'status' => Revision::STATUS_PENDING,
        ]);

        // Use action to handle approval workflow
        \App\Actions\Revisions\HandleRevisionSubmission::run($revision);

        return $revision;
    }

    /**
     * Apply an approved revision to this model.
     */
    public function applyRevision(Revision $revision): bool
    {
        if (! $revision->isApproved()) {
            throw new \InvalidArgumentException('Cannot apply unapproved revision');
        }

        if ($revision->revisionable_id !== $this->getKey() || $revision->revisionable_type !== static::class) {
            throw new \InvalidArgumentException('Revision does not belong to this model');
        }

        // Temporarily disable revision checking
        $this->setRequiresRevisions(false);

        try {
            // Decode any JSON strings in proposed changes
            $changes = $this->decodeJsonStrings($revision->proposed_changes);

            // Let Laravel's normal casting handle it
            $result = $this->update($changes);

            // Mark revision as applied
            $revision->update([
                'status' => Revision::STATUS_APPROVED,
                'reviewed_at' => now(),
            ]);

            return $result;
        } finally {
            // Re-enable revision checking
            $this->setRequiresRevisions(true);
        }
    }

    /**
     * Decode JSON strings in attributes array.
     */
    protected function decodeJsonStrings(array $attributes): array
    {
        $decoded = [];

        foreach ($attributes as $key => $value) {
            // If value is a JSON string, decode it to array
            if (is_string($value) && $this->isJson($value)) {
                $decoded[$key] = json_decode($value, true);
            } else {
                $decoded[$key] = $value;
            }
        }

        return $decoded;
    }

    /**
     * Check if a string is valid JSON.
     */
    protected function isJson(string $value): bool
    {
        if (! str_starts_with($value, '{') && ! str_starts_with($value, '[')) {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Override the standard update method to handle revisions.
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        // If revisions are disabled or this is an exempt update, proceed normally
        if (! $this->shouldCreateRevision($attributes)) {
            return parent::update($attributes, $options);
        }

        // Create a revision instead of updating directly
        $revision = $this->createRevision($attributes, Auth::user());

        // If revision was auto-approved, the changes have been applied
        $revision->refresh();
        if ($revision->status === Revision::STATUS_APPROVED) {
            return true;
        }

        // Return false if revision is still pending
        return false;
    }

    /**
     * Determine if a revision should be created for the given attributes.
     */
    protected function shouldCreateRevision(?array $attributes = null): bool
    {
        // Don't create revisions if disabled
        if (! static::$requiresRevisions) {
            return false;
        }

        // Don't create revisions if no user is authenticated (system updates)
        if (! Auth::id()) {
            return false;
        }

        // Don't create revisions if only exempt fields are being updated
        $attributes = $attributes ?? $this->getDirty();
        $revisionableChanges = $this->filterRevisionExemptFields($attributes);

        return ! empty($revisionableChanges);
    }

    /**
     * Filter out exempt fields from the changes.
     */
    protected function filterRevisionExemptFields(array $attributes): array
    {
        $exemptFields = array_merge(
            $this->revisionExemptFields,
            $this->getRevisionExemptFields()
        );

        return array_diff_key($attributes, array_flip($exemptFields));
    }

    /**
     * Get model-specific exempt fields.
     * Override in individual models to customize.
     */
    protected function getRevisionExemptFields(): array
    {
        return [];
    }

    /**
     * Intercept the updating event to handle revisions.
     */
    protected function interceptUpdate(): bool
    {
        // Create revision and prevent normal update
        $this->createRevision();

        return false; // Prevents the update from proceeding
    }

    /**
     * Temporarily disable revision requirements.
     */
    public function setRequiresRevisions(bool $required): void
    {
        static::$requiresRevisions = $required;
    }

    /**
     * Force update without revision (admin/system use).
     */
    public function forceUpdate(array $attributes = [], array $options = []): bool
    {
        $originalRequirement = static::$requiresRevisions;
        static::$requiresRevisions = false;

        try {
            // Decode any JSON strings
            $attributes = $this->decodeJsonStrings($attributes);

            return parent::update($attributes, $options);
        } finally {
            static::$requiresRevisions = $originalRequirement;
        }
    }

    /**
     * Get the auto-approval mode for this model.
     */
    public function getAutoApproveMode(): string
    {
        return property_exists($this, 'autoApprove') ? $this->autoApprove : 'trusted';
    }

    /**
     * Get revision configuration for this model.
     */
    public function getRevisionConfig(): array
    {
        return [
            'requires_revisions' => static::$requiresRevisions,
            'exempt_fields' => array_merge($this->revisionExemptFields, $this->getRevisionExemptFields()),
            'auto_approve_threshold' => $this->getAutoApproveThreshold(),
            'auto_approve_mode' => $this->getAutoApproveMode(),
        ];
    }

    /**
     * Get the trust threshold for auto-approval.
     * Override in models to customize.
     */
    protected function getAutoApproveThreshold(): int
    {
        return 30; // Default to auto-approved trust level
    }

    /**
     * Get revision summary for display.
     */
    public function getRevisionSummary(): array
    {
        $revisions = $this->revisions();

        return [
            'total' => $revisions->count(),
            'pending' => $this->pendingRevisions()->count(),
            'approved' => $revisions->approved()->count(),
            'rejected' => $revisions->rejected()->count(),
            'latest' => $this->latestRevision(),
        ];
    }

    /**
     * Check if user can submit revisions for this model.
     */
    public function canUserSubmitRevisions(User $user): bool
    {
        // Override in individual models for custom authorization
        return true;
    }

    /**
     * Check if user can approve revisions for this model.
     */
    public function canUserApproveRevisions(User $user): bool
    {
        // Override in individual models for custom authorization
        return $user->hasPermissionTo('approve revisions') ?? false;
    }

    /**
     * Get revision workflow information for this content.
     */
    public function getRevisionWorkflow(): array
    {
        $autoApproveMode = $this->getAutoApproveMode();
        $contentCategory = $this->getContentCategory();

        // Base workflow info
        $workflow = [
            'auto_approve_mode' => $autoApproveMode,
            'content_category' => $contentCategory,
        ];

        switch ($autoApproveMode) {
            case 'never':
                $workflow = array_merge($workflow, [
                    'requires_approval' => true,
                    'review_priority' => 'priority',
                    'estimated_review_time' => 12, // Same-day for organizational content
                    'reason' => 'Organizational content requires manual approval',
                ]);
                break;

            case 'personal':
                $currentUser = Auth::user();
                if ($currentUser) {
                    // Use fully qualified classname as trust points key
                    $trustPointsKey = static::class;
                    $trustPoints = $currentUser->trust_points[$trustPointsKey] ?? 0;
                    $inPoorStanding = $trustPoints < -5;

                    $workflow = array_merge($workflow, [
                        'requires_approval' => $inPoorStanding,
                        'review_priority' => $inPoorStanding ? 'standard' : 'none',
                        'estimated_review_time' => $inPoorStanding ? 48 : 0,
                        'reason' => $inPoorStanding ? 'Poor standing requires approval' : 'Personal content auto-approved',
                    ]);
                } else {
                    $workflow = array_merge($workflow, [
                        'requires_approval' => false,
                        'review_priority' => 'none',
                        'estimated_review_time' => 0,
                        'reason' => 'Personal content auto-approved',
                    ]);
                }
                break;

            case 'trusted':
            default:
                $currentUser = Auth::user();
                if ($currentUser) {
                    $trustWorkflow = DetermineApprovalWorkflow::run($currentUser, static::class);

                    $workflow = array_merge($workflow, [
                        'requires_approval' => $trustWorkflow['requires_approval'],
                        'review_priority' => $trustWorkflow['review_priority'],
                        'estimated_review_time' => $trustWorkflow['estimated_review_time'],
                        'reason' => 'Public content requires trust-based approval',
                    ]);
                } else {
                    $workflow = array_merge($workflow, [
                        'requires_approval' => true,
                        'review_priority' => 'standard',
                        'estimated_review_time' => 72,
                        'reason' => 'Public content requires trust-based approval',
                    ]);
                }
                break;
        }

        return $workflow;
    }

    /**
     * Get content category for workflow determination.
     */
    protected function getContentCategory(): string
    {
        switch ($this->getAutoApproveMode()) {
            case 'personal':
                return 'personal';
            case 'never':
                return 'organizational';
            case 'trusted':
            case 'untilReport':
            default:
                return 'public';
        }
    }
}
