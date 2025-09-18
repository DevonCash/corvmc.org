<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Tracks equipment damage reports and repair workflows.
 * 
 * Similar to GitHub issues for tracking equipment problems from discovery
 * through completion of repairs, with assignment, priority, and cost tracking.
 *
 * @property int $id
 * @property int $equipment_id
 * @property int|null $equipment_loan_id
 * @property int $reported_by_id
 * @property int|null $assigned_to_id
 * @property string $title
 * @property string $description
 * @property string $severity
 * @property string $status
 * @property string $priority
 * @property string|null $repair_notes
 * @property \Illuminate\Support\Carbon $discovered_at
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property int|null $estimated_cost
 * @property int|null $actual_cost
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\User|null $assignedTo
 * @property-read \App\Models\Equipment $equipment
 * @property-read int $days_open
 * @property-read bool $is_high_priority
 * @property-read bool $is_open
 * @property-read string $priority_color
 * @property-read string $severity_color
 * @property-read string $status_color
 * @property-read \App\Models\EquipmentLoan|null $loan
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \App\Models\User $reportedBy
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport assigned()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport bySeverity(string $severity)
 * @method static \Database\Factories\EquipmentDamageReportFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport forEquipment(\App\Models\Equipment $equipment)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport highPriority()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport open()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport unassigned()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport whereActualCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport whereAssignedToId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport whereDiscoveredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport whereEquipmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport whereEquipmentLoanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport whereEstimatedCost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport whereRepairNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport whereReportedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport whereSeverity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EquipmentDamageReport withoutTrashed()
 * @mixin \Eloquent
 */
class EquipmentDamageReport extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia, LogsActivity;

    protected $fillable = [
        'equipment_id',
        'equipment_loan_id',
        'reported_by_id',
        'assigned_to_id',
        'title',
        'description',
        'severity',
        'status',
        'priority',
        'estimated_cost',
        'actual_cost',
        'repair_notes',
        'discovered_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'estimated_cost' => 'integer',
        'actual_cost' => 'integer',
        'discovered_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'severity' => 'medium',
        'status' => 'reported',
        'priority' => 'normal',
    ];

    /**
     * Get the equipment this damage report is for.
     */
    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    /**
     * Get the loan that discovered this damage (if applicable).
     */
    public function loan()
    {
        return $this->belongsTo(EquipmentLoan::class, 'equipment_loan_id');
    }

    /**
     * Get the user who reported the damage.
     */
    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }

    /**
     * Get the user assigned to fix the damage.
     */
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /**
     * Check if damage report is still open.
     */
    public function getIsOpenAttribute(): bool
    {
        return !in_array($this->status, ['completed', 'cancelled']);
    }

    /**
     * Check if damage report is high priority.
     */
    public function getIsHighPriorityAttribute(): bool
    {
        return in_array($this->priority, ['high', 'urgent']) || 
               in_array($this->severity, ['high', 'critical']);
    }

    /**
     * Get number of days the report has been open.
     */
    public function getDaysOpenAttribute(): int
    {
        $endDate = $this->completed_at ?? now();
        return (int) $this->discovered_at->diffInDays($endDate);
    }

    /**
     * Get severity badge color for UI.
     */
    public function getSeverityColorAttribute(): string
    {
        return match($this->severity) {
            'low' => 'success',
            'medium' => 'warning',
            'high' => 'danger',
            'critical' => 'danger',
            default => 'gray'
        };
    }

    /**
     * Get priority badge color for UI.
     */
    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            'low' => 'gray',
            'normal' => 'info',
            'high' => 'warning',
            'urgent' => 'danger',
            default => 'gray'
        };
    }

    /**
     * Get status badge color for UI.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'reported' => 'info',
            'in_progress' => 'warning',
            'waiting_parts' => 'warning',
            'completed' => 'success',
            'cancelled' => 'gray',
            default => 'gray'
        };
    }

    /**
     * Mark damage report as started.
     */
    public function markStarted(?int $assignedToId = null): void
    {
        $this->update([
            'status' => 'in_progress',
            'started_at' => now(),
            'assigned_to_id' => $assignedToId ?? $this->assigned_to_id,
        ]);
    }

    /**
     * Mark damage report as completed.
     */
    public function markCompleted(string $repairNotes = null, ?int $actualCost = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'repair_notes' => $repairNotes,
            'actual_cost' => $actualCost,
        ]);

        // Update equipment condition if repaired
        if ($actualCost !== null && $actualCost > 0) {
            $this->equipment->update(['condition' => 'good']);
        }
    }

    /**
     * Assign damage report to a user.
     */
    public function assignTo(User $user): void
    {
        $this->update(['assigned_to_id' => $user->id]);
    }

    /**
     * Update priority level.
     */
    public function setPriority(string $priority): void
    {
        $this->update(['priority' => $priority]);
    }

    /**
     * Scope for open damage reports.
     */
    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }

    /**
     * Scope for high priority reports.
     */
    public function scopeHighPriority($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('priority', ['high', 'urgent'])
              ->orWhereIn('severity', ['high', 'critical']);
        });
    }

    /**
     * Scope for assigned reports.
     */
    public function scopeAssigned($query)
    {
        return $query->whereNotNull('assigned_to_id');
    }

    /**
     * Scope for unassigned reports.
     */
    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to_id');
    }

    /**
     * Scope by severity level.
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope by equipment.
     */
    public function scopeForEquipment($query, Equipment $equipment)
    {
        return $query->where('equipment_id', $equipment->id);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'assigned_to_id', 'priority', 'severity', 'actual_cost'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Damage report {$eventName}");
    }
}