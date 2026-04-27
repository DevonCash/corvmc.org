<?php

/**
 * NotSpamEmail Validation Rule Tests
 *
 * Tests that the NotSpamEmail rule correctly handles the array response
 * from SpamPreventionService::checkEmailAgainstStopForumSpam().
 */

use App\Rules\NotSpamEmail;
use CorvMC\Moderation\Facades\SpamPreventionService;

describe('NotSpamEmail rule', function () {
    it('passes when email is not flagged as spam', function () {
        SpamPreventionService::shouldReceive('checkEmailAgainstStopForumSpam')
            ->once()
            ->with('good@example.com')
            ->andReturn([
                'is_spam' => false,
                'frequency' => 0,
                'last_seen' => null,
                'confidence' => 0,
                'source' => 'stopforumspam',
            ]);

        $rule = new NotSpamEmail;
        $failed = false;

        $rule->validate('email', 'good@example.com', function () use (&$failed) {
            $failed = true;
        });

        expect($failed)->toBeFalse();
    });

    it('fails when email is flagged as spam', function () {
        SpamPreventionService::shouldReceive('checkEmailAgainstStopForumSpam')
            ->once()
            ->with('spammer@example.com')
            ->andReturn([
                'is_spam' => true,
                'frequency' => 42,
                'last_seen' => '2026-04-01',
                'confidence' => 90,
                'source' => 'stopforumspam',
            ]);

        $rule = new NotSpamEmail;
        $failMessage = null;

        $rule->validate('email', 'spammer@example.com', function ($message) use (&$failMessage) {
            $failMessage = $message;
        });

        expect($failMessage)->toContain('identified as spam');
    });

    it('passes when spam service returns unknown result', function () {
        SpamPreventionService::shouldReceive('checkEmailAgainstStopForumSpam')
            ->once()
            ->andReturn([
                'is_spam' => false,
                'frequency' => 0,
                'last_seen' => null,
                'confidence' => 0,
                'source' => 'unknown',
            ]);

        $rule = new NotSpamEmail;
        $failed = false;

        $rule->validate('email', 'unknown@example.com', function () use (&$failed) {
            $failed = true;
        });

        expect($failed)->toBeFalse();
    });
});
