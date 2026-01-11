<?php

namespace App\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class SpamCheckResultData extends Data
{
    public function __construct(
        public bool $appears,
        public int $frequency,
        public ?Carbon $last_seen,
        public bool $is_spam,
        public ?string $error = null,
    ) {}

    /**
     * Create from StopForumSpam API response
     */
    public static function fromApiResponse(array $response): self
    {
        $emailData = $response['email'] ?? [];

        return new self(
            appears: (bool) ($emailData['appears'] ?? false),
            frequency: (int) ($emailData['frequency'] ?? 0),
            last_seen: isset($emailData['lastseen'])
                ? Carbon::parse($emailData['lastseen'])
                : null,
            is_spam: (bool) ($emailData['appears'] ?? false) && ($emailData['frequency'] ?? 0) > 0,
            error: null,
        );
    }

    /**
     * Create error result when API fails
     */
    public static function error(string $message): self
    {
        return new self(
            appears: false,
            frequency: 0,
            last_seen: null,
            is_spam: false,
            error: $message,
        );
    }

    /**
     * Create clean result (not spam)
     */
    public static function clean(): self
    {
        return new self(
            appears: false,
            frequency: 0,
            last_seen: null,
            is_spam: false,
            error: null,
        );
    }

    public function hasError(): bool
    {
        return $this->error !== null;
    }
}
