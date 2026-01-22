<?php

namespace CorvMC\Moderation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Revision Model
 *
 * Represents pending changes to any model that require moderator approval.
 * Stores original data, proposed changes, and approval workflow state.
 *
 * @property int $id
 * @property string $revisionable_type
 * @property int $revisionable_id
 * @property array<array-key, mixed> $original_data
 * @property array<array-key, mixed> $proposed_changes
 * @property string $status
 * @property int $submitted_by_id
 * @property int|null $reviewed_by_id
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property string|null $review_reason
 * @property string $revision_type
 * @property bool $auto_approved
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\User|null $reviewedBy
 * @property-read Model|\Eloquent $revisionable
 * @property-read \App\Models\User $submittedBy
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision approved()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision autoApproved()
 * @method static \Database\Factories\RevisionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision forModelType(string $modelType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision olderThan(int $days)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision rejected()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision submittedBy(\App\Models\User $user)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision whereAutoApproved($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision whereOriginalData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision whereProposedChanges($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision whereReviewReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision whereReviewedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision whereReviewedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision whereRevisionType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision whereRevisionableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision whereRevisionableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision whereSubmittedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Revision whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Revision extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'revisionable_type',
        'revisionable_id',
        'original_data',
        'proposed_changes',
        'status',
        'submitted_by_id',
        'reviewed_by_id',
        'reviewed_at',
        'review_reason',
        'revision_type',
        'auto_approved',
    ];

    protected $casts = [
        'original_data' => 'array',
        'proposed_changes' => 'array',
        'reviewed_at' => 'datetime',
        'auto_approved' => 'boolean',
    ];

    /**
     * Revision status constants
     */
    const STATUS_PENDING = 'pending';

    const STATUS_APPROVED = 'approved';

    const STATUS_REJECTED = 'rejected';

    /**
     * Revision type constants
     */
    const TYPE_UPDATE = 'update';

    const TYPE_CREATE = 'create';

    const TYPE_DELETE = 'delete';

    /**
     * Get the model that this revision applies to.
     */
    public function revisionable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who submitted this revision.
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    /**
     * Get the user who reviewed this revision.
     */
    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /**
     * Check if this revision is pending review.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if this revision has been approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if this revision has been rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if this revision has been reviewed.
     */
    public function isReviewed(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_REJECTED]);
    }

    /**
     * Get available appeal reviewers for this revision.
     * TODO: Implement appeal system
     */
    public function getAppealReviewers()
    {
        // Stub method for now - return empty array
        return [];
    }

    /**
     * Create an appeal for this revision.
     * TODO: Implement appeal system
     */
    public function createAppeal($submitter, $reason)
    {
        // Stub method for now - return a fake appeal object
        return (object) [
            'id' => 1,
            'revision_id' => $this->id,
            'submitter_id' => $submitter->id,
            'reason' => $reason,
            'status' => 'pending',
        ];
    }

    /**
     * Check if this revision can be appealed.
     * TODO: Implement appeal system
     */
    public function canBeAppealed(): bool
    {
        // Stub method - for now, rejected revisions can be appealed
        return $this->isRejected();
    }

    /**
     * Get the appeal deadline for this revision.
     * TODO: Implement appeal system
     */
    public function getAppealDeadline()
    {
        // Stub method - return 30 days from now
        return now()->addDays(30);
    }

    /**
     * Get appeal patterns for analysis.
     * TODO: Implement appeal system
     */
    public function getAppealPatterns()
    {
        // Stub method for now - return empty array
        return [];
    }

    /**
     * Get the changes as a diff-friendly format.
     */
    public function getChanges(): array
    {
        $changes = [];

        foreach ($this->proposed_changes as $field => $newValue) {
            $originalValue = $this->original_data[$field] ?? null;

            if ($originalValue !== $newValue) {
                $changes[$field] = [
                    'from' => $originalValue,
                    'to' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Get a summary of what changed.
     */
    public function getChangesSummary(): string
    {
        $changes = $this->getChanges();
        $count = count($changes);

        if ($count === 0) {
            return 'No changes';
        }

        if ($count === 1) {
            $field = array_key_first($changes);

            return "Updated {$field}";
        }

        return "Updated {$count} fields: ".implode(', ', array_keys($changes));
    }

    /**
     * Get the human-readable model type name.
     */
    public function getModelTypeName(): string
    {
        return match ($this->revisionable_type) {
            'App\Models\MemberProfile' => 'Member Profile',
            'App\Models\Band' => 'Band',
            'CorvMC\Events\Models\Event' => 'Event',
            default => class_basename($this->revisionable_type)
        };
    }

    /**
     * Get a human-readable title for the revisionable model.
     * Uses the model's getRevisionableTitle() method if available,
     * otherwise falls back to type + ID.
     */
    public function getRevisionableTitle(): string
    {
        $revisionable = $this->revisionable;

        if (! $revisionable) {
            return $this->getModelTypeName() . ' #' . $this->revisionable_id;
        }

        if (method_exists($revisionable, 'getRevisionableTitle')) {
            return $revisionable->getRevisionableTitle();
        }

        return $this->getModelTypeName() . ' #' . $this->revisionable_id;
    }

    /**
     * Scope for pending revisions.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for approved revisions.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for rejected revisions.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope for auto-approved revisions.
     */
    public function scopeAutoApproved($query)
    {
        return $query->where('auto_approved', true);
    }

    /**
     * Scope for revisions by model type.
     */
    public function scopeForModelType($query, string $modelType)
    {
        return $query->where('revisionable_type', $modelType);
    }

    /**
     * Scope for revisions submitted by a specific user.
     */
    public function scopeSubmittedBy($query, User $user)
    {
        return $query->where('submitted_by_id', $user->id);
    }

    /**
     * Scope for revisions older than a certain number of days.
     */
    public function scopeOlderThan($query, int $days)
    {
        return $query->where('created_at', '<', now()->subDays($days));
    }

    /**
     * Activity log configuration.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'reviewed_by_id', 'review_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function (string $eventName) {
                return match ($eventName) {
                    'created' => 'Revision submitted for review',
                    'updated' => $this->getStatusChangeDescription(),
                    'deleted' => 'Revision removed',
                    default => "Revision {$eventName}",
                };
            });
    }

    /**
     * Get description for status changes.
     */
    private function getStatusChangeDescription(): string
    {
        if ($this->isDirty('status')) {
            $from = $this->getOriginal('status');
            $to = $this->status;

            return match ([$from, $to]) {
                ['pending', 'approved'] => 'Revision approved',
                ['pending', 'rejected'] => 'Revision rejected',
                default => 'Revision status updated',
            };
        }

        return 'Revision updated';
    }
}
