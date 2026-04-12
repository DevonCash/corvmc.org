<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Service for managing activity logs.
 * 
 * This service handles cleanup and maintenance of activity logs
 * throughout the application.
 */
class ActivityLogService
{
    /**
     * Clean up old activity logs.
     * 
     * @param int $daysToKeep Number of days to keep logs (default: 90)
     * @return int Number of logs deleted
     */
    public function cleanup(int $daysToKeep = 90): int
    {
        return Activity::where('created_at', '<', now()->subDays($daysToKeep))->delete();
    }

    /**
     * Clean up logs for a specific log name.
     * 
     * @param string $logName The log name to clean up
     * @param int $daysToKeep Number of days to keep logs
     * @return int Number of logs deleted
     */
    public function cleanupByLogName(string $logName, int $daysToKeep = 90): int
    {
        return Activity::where('log_name', $logName)
            ->where('created_at', '<', now()->subDays($daysToKeep))
            ->delete();
    }

    /**
     * Clean up logs for a specific subject type.
     * 
     * @param string $subjectType The subject type to clean up (e.g., 'App\Models\User')
     * @param int $daysToKeep Number of days to keep logs
     * @return int Number of logs deleted
     */
    public function cleanupBySubjectType(string $subjectType, int $daysToKeep = 90): int
    {
        return Activity::where('subject_type', $subjectType)
            ->where('created_at', '<', now()->subDays($daysToKeep))
            ->delete();
    }

    /**
     * Get activity log statistics.
     * 
     * @return array
     */
    public function getStats(): array
    {
        return [
            'total_logs' => Activity::count(),
            'logs_today' => Activity::whereDate('created_at', today())->count(),
            'logs_this_week' => Activity::where('created_at', '>=', now()->subDays(7))->count(),
            'logs_this_month' => Activity::where('created_at', '>=', now()->subDays(30))->count(),
            'logs_by_type' => $this->getLogsByType(),
            'top_users' => $this->getTopActiveUsers(),
            'storage_size' => $this->estimateStorageSize(),
        ];
    }

    /**
     * Get activity count by log name.
     * 
     * @return array
     */
    protected function getLogsByType(): array
    {
        return Activity::selectRaw('log_name, COUNT(*) as count')
            ->groupBy('log_name')
            ->pluck('count', 'log_name')
            ->toArray();
    }

    /**
     * Get top active users based on activity logs.
     * 
     * @param int $limit
     * @return array
     */
    protected function getTopActiveUsers(int $limit = 10): array
    {
        return Activity::selectRaw('causer_id, causer_type, COUNT(*) as activity_count')
            ->whereNotNull('causer_id')
            ->where('causer_type', 'App\\Models\\User')
            ->groupBy('causer_id', 'causer_type')
            ->orderByDesc('activity_count')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'user_id' => $item->causer_id,
                    'activity_count' => $item->activity_count,
                ];
            })
            ->toArray();
    }

    /**
     * Estimate the storage size used by activity logs.
     * 
     * @return array
     */
    protected function estimateStorageSize(): array
    {
        // Rough estimate based on average log entry size
        $totalLogs = Activity::count();
        $averageSizePerLog = 1024; // 1KB average per log entry
        $totalSizeBytes = $totalLogs * $averageSizePerLog;

        return [
            'bytes' => $totalSizeBytes,
            'formatted' => $this->formatBytes($totalSizeBytes),
        ];
    }

    /**
     * Format bytes into human-readable format.
     * 
     * @param int $bytes
     * @return string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Archive old logs to a separate table or storage.
     * 
     * @param int $daysToArchive Logs older than this many days will be archived
     * @return int Number of logs archived
     */
    public function archiveOldLogs(int $daysToArchive = 365): int
    {
        // This is a placeholder for archiving functionality
        // In a real implementation, you might:
        // 1. Move old logs to an archive table
        // 2. Export to external storage (S3, etc.)
        // 3. Compress and store as files
        
        $logsToArchive = Activity::where('created_at', '<', now()->subDays($daysToArchive))->count();
        
        // TODO: Implement actual archiving logic
        
        return $logsToArchive;
    }

    /**
     * Prune logs based on multiple criteria.
     * 
     * @param array $criteria
     * @return int Number of logs deleted
     */
    public function pruneLogs(array $criteria = []): int
    {
        $query = Activity::query();

        // Apply age criteria
        if (isset($criteria['older_than_days'])) {
            $query->where('created_at', '<', now()->subDays($criteria['older_than_days']));
        }

        // Apply log name filter
        if (isset($criteria['log_name'])) {
            $query->where('log_name', $criteria['log_name']);
        }

        // Apply subject type filter
        if (isset($criteria['subject_type'])) {
            $query->where('subject_type', $criteria['subject_type']);
        }

        // Apply event filter
        if (isset($criteria['event'])) {
            $query->where('event', $criteria['event']);
        }

        // Keep only the most recent N logs if specified
        if (isset($criteria['keep_recent'])) {
            $idsToKeep = Activity::orderByDesc('created_at')
                ->limit($criteria['keep_recent'])
                ->pluck('id');
            
            $query->whereNotIn('id', $idsToKeep);
        }

        return $query->delete();
    }
}