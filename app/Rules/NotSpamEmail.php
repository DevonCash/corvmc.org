<?php

namespace App\Rules;

use CorvMC\Moderation\Actions\SpamPrevention\CheckEmailAgainstStopForumSpam;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;

class NotSpamEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $spamCheck = CheckEmailAgainstStopForumSpam::run($value);

        if ($spamCheck->is_spam) {
            // Log the blocked attempt to activity log
            activity()
                ->withProperties([
                    'email_hash' => md5(strtolower(trim($value))),
                    'frequency' => $spamCheck->frequency,
                    'last_seen' => $spamCheck->last_seen?->toIso8601String(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ])
                ->log('spam_registration_blocked');

            Log::warning('Registration blocked - email in spam database', [
                'email_hash' => md5(strtolower(trim($value))),
                'frequency' => $spamCheck->frequency,
                'ip' => request()->ip(),
            ]);

            $fail('This email address has been identified as spam. Please contact support if this is an error.');
        }
    }
}
