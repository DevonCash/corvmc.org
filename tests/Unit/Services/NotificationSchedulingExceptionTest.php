<?php

use App\Exceptions\Services\NotificationSchedulingException;
use Carbon\Carbon;

describe('NotificationSchedulingException static methods', function () {
    it('creates invalid inactive days exception', function () {
        $exception = NotificationSchedulingException::invalidInactiveDaysValue(-5);
        
        expect($exception)->toBeInstanceOf(NotificationSchedulingException::class)
            ->and($exception->getMessage())->toContain('Invalid inactive days value: -5')
            ->and($exception->getMessage())->toContain('Must be a positive integer greater than 0');
    });

    it('creates notification delivery failed exception', function () {
        $exception = NotificationSchedulingException::notificationDeliveryFailed(
            'ReservationReminder',
            'test@example.com',
            'SMTP connection failed'
        );
        
        expect($exception)->toBeInstanceOf(NotificationSchedulingException::class)
            ->and($exception->getMessage())->toContain('Failed to deliver ReservationReminder notification')
            ->and($exception->getMessage())->toContain('test@example.com')
            ->and($exception->getMessage())->toContain('SMTP connection failed');
    });

    it('creates invalid notification class exception', function () {
        $exception = NotificationSchedulingException::invalidNotificationClass('App\\Models\\User');
        
        expect($exception)->toBeInstanceOf(NotificationSchedulingException::class)
            ->and($exception->getMessage())->toContain('Invalid notification class: App\\Models\\User')
            ->and($exception->getMessage())->toContain('Must implement Illuminate\\Notifications\\Notification');
    });

    it('creates user not found exception', function () {
        $exception = NotificationSchedulingException::userNotFound(999);
        
        expect($exception)->toBeInstanceOf(NotificationSchedulingException::class)
            ->and($exception->getMessage())->toContain('User with ID 999 not found')
            ->and($exception->getMessage())->toContain('notification scheduling');
    });

    it('creates reservation not found exception', function () {
        $exception = NotificationSchedulingException::reservationNotFound(123);
        
        expect($exception)->toBeInstanceOf(NotificationSchedulingException::class)
            ->and($exception->getMessage())->toContain('Reservation with ID 123 not found')
            ->and($exception->getMessage())->toContain('for notification');
    });

    it('creates invalid scheduling date exception', function () {
        $pastDate = Carbon::now()->subDay();
        $exception = NotificationSchedulingException::invalidSchedulingDate($pastDate);
        
        expect($exception)->toBeInstanceOf(NotificationSchedulingException::class)
            ->and($exception->getMessage())->toContain('Invalid scheduling date')
            ->and($exception->getMessage())->toContain($pastDate->format('Y-m-d H:i:s'))
            ->and($exception->getMessage())->toContain('Cannot schedule notifications for past dates');
    });

    it('creates notification type not supported exception', function () {
        $exception = NotificationSchedulingException::notificationTypeNotSupported('CustomType');
        
        expect($exception)->toBeInstanceOf(NotificationSchedulingException::class)
            ->and($exception->getMessage())->toContain('Notification type \'CustomType\' is not supported')
            ->and($exception->getMessage())->toContain('scheduling service');
    });

    it('creates bulk notification failed exception with few errors', function () {
        $errors = [
            'User 1: Email failed',
            'User 2: Invalid address',
        ];
        
        $exception = NotificationSchedulingException::bulkNotificationFailed($errors);
        
        expect($exception)->toBeInstanceOf(NotificationSchedulingException::class)
            ->and($exception->getMessage())->toContain('Bulk notification operation failed with 2 errors')
            ->and($exception->getMessage())->toContain('User 1: Email failed')
            ->and($exception->getMessage())->toContain('User 2: Invalid address');
    });

    it('creates bulk notification failed exception with many errors', function () {
        $errors = [
            'User 1: Email failed',
            'User 2: Invalid address',
            'User 3: Bounced email',
            'User 4: Timeout',
            'User 5: Server error',
        ];
        
        $exception = NotificationSchedulingException::bulkNotificationFailed($errors);
        
        expect($exception)->toBeInstanceOf(NotificationSchedulingException::class)
            ->and($exception->getMessage())->toContain('Bulk notification operation failed with 5 errors')
            ->and($exception->getMessage())->toContain('User 1: Email failed')
            ->and($exception->getMessage())->toContain('User 2: Invalid address')
            ->and($exception->getMessage())->toContain('User 3: Bounced email')
            ->and($exception->getMessage())->toContain('and 2 more errors');
    });
});