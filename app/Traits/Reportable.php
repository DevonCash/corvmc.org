<?php

namespace App\Traits;

use App\Models\Report;

trait Reportable
{
    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }
    
    public function pendingReports()
    {
        return $this->reports()->where('status', 'pending');
    }
    
    public function upheldReports()
    {
        return $this->reports()->where('status', 'upheld');
    }
    
    public function getReportSummary(): array
    {
        return [
            'total' => $this->reports()->count(),
            'pending' => $this->pendingReports()->count(), 
            'upheld' => $this->upheldReports()->count(),
            'dismissed' => $this->reports()->where('status', 'dismissed')->count(),
        ];
    }
    
    public function hasReachedReportThreshold(): bool
    {
        $pendingCount = $this->pendingReports()->count();
        return $pendingCount >= $this->getReportThreshold();
    }
    
    // Get report threshold - uses static property or default
    public function getReportThreshold(): int
    {
        return static::$reportThreshold ?? 3;
    }
    
    // Check if content should be auto-hidden when threshold reached
    public function shouldAutoHide(): bool
    {
        return static::$reportAutoHide ?? false;
    }
    
    // Get human-readable content type name
    public function getReportableType(): string
    {
        return static::$reportableTypeName ?? class_basename(static::class);
    }
    
    // Check if user has already reported this content
    public function hasBeenReportedBy($user): bool
    {
        return $this->reports()
            ->where('reported_by_id', $user->id)
            ->where('status', 'pending')
            ->exists();
    }
    
    // Get the most common report reason for this content
    public function getMostCommonReportReason(): ?string
    {
        $mostCommon = $this->reports()
            ->selectRaw('reason, COUNT(*) as count')
            ->groupBy('reason')
            ->orderByDesc('count')
            ->first();
            
        return $mostCommon?->reason;
    }
}