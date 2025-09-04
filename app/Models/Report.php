<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Report extends Model
{
    use LogsActivity;
    
    protected $fillable = [
        'reportable_type',
        'reportable_id', 
        'reported_by_id',
        'reason',
        'custom_reason',
        'status',
        'resolved_by_id',
        'resolved_at', 
        'resolution_notes',
    ];
    
    protected $casts = [
        'resolved_at' => 'datetime',
    ];
    
    // Report reasons - context-aware
    public const REASONS = [
        'inappropriate_content' => 'Inappropriate Content',
        'spam' => 'Spam or Duplicate',
        'misleading_info' => 'Misleading Information',
        'harassment' => 'Harassment or Abuse',
        'fake_profile' => 'Fake Profile',
        'copyright' => 'Copyright Violation',
        'policy_violation' => 'Policy Violation',
        'other' => 'Other (specify)',
    ];
    
    public const STATUSES = [
        'pending' => 'Pending Review',
        'upheld' => 'Upheld', 
        'dismissed' => 'Dismissed',
        'escalated' => 'Escalated',
    ];
    
    public function reportable()
    {
        return $this->morphTo();
    }
    
    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }
    
    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }
    
    // Get context-appropriate reasons based on reportable type
    public static function getReasonsForType(string $type): array
    {
        $baseReasons = ['inappropriate_content', 'spam', 'harassment', 'policy_violation', 'other'];
        
        return match($type) {
            'App\Models\Production' => array_merge($baseReasons, ['misleading_info']),
            'App\Models\MemberProfile' => array_merge($baseReasons, ['fake_profile']),
            'App\Models\Band' => array_merge($baseReasons, ['copyright', 'misleading_info']),
            default => $baseReasons,
        };
    }
    
    // Get human readable reason label
    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
    
    // Get human readable status label
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
    
    // Check if report is resolved
    public function isResolved(): bool
    {
        return in_array($this->status, ['upheld', 'dismissed']);
    }
    
    // Check if report is pending
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
    
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['reason', 'status', 'resolved_by_id', 'resolution_notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(function(string $eventName) {
                return match($eventName) {
                    'created' => 'Report submitted',
                    'updated' => $this->getStatusChangeDescription(),
                    'deleted' => 'Report removed',
                    default => "Report {$eventName}",
                };
            });
    }
    
    private function getStatusChangeDescription(): string
    {
        if ($this->isDirty('status')) {
            $from = $this->getOriginal('status');
            $to = $this->status;
            
            return match([$from, $to]) {
                ['pending', 'upheld'] => 'Report upheld by moderator',
                ['pending', 'dismissed'] => 'Report dismissed by moderator',
                ['pending', 'escalated'] => 'Report escalated for review',
                default => 'Report status updated',
            };
        }
        
        return 'Report updated';
    }
}
