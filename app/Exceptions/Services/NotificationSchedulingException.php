<?php

namespace App\Exceptions\Services;

use Exception;

class NotificationSchedulingException extends Exception
{
    public static function invalidInactiveDaysValue(int $days): self
    {
        return new self("Invalid inactive days value: {$days}. Must be a positive integer greater than 0");
    }

    public static function notificationDeliveryFailed(string $notificationType, string $userEmail, string $reason): self
    {
        return new self("Failed to deliver {$notificationType} notification to {$userEmail}: {$reason}");
    }

    public static function invalidNotificationClass(string $className): self
    {
        return new self("Invalid notification class: {$className}. Must implement Illuminate\\Notifications\\Notification");
    }

    public static function userNotFound(int $userId): self
    {
        return new self("User with ID {$userId} not found for notification scheduling");
    }

    public static function reservationNotFound(int $reservationId): self
    {
        return new self("Reservation with ID {$reservationId} not found for notification");
    }

    public static function invalidSchedulingDate(\DateTimeInterface $date): self
    {
        return new self("Invalid scheduling date: {$date->format('Y-m-d H:i:s')}. Cannot schedule notifications for past dates");
    }

    public static function notificationTypeNotSupported(string $type): self
    {
        return new self("Notification type '{$type}' is not supported by the scheduling service");
    }

    public static function bulkNotificationFailed(array $errors): self
    {
        $errorCount = count($errors);
        $errorSummary = implode('; ', array_slice($errors, 0, 3));
        if ($errorCount > 3) {
            $errorSummary .= " and " . ($errorCount - 3) . " more errors";
        }
        
        return new self("Bulk notification operation failed with {$errorCount} errors: {$errorSummary}");
    }
}