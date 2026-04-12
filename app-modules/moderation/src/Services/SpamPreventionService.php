<?php

namespace CorvMC\Moderation\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for spam prevention and detection.
 * 
 * This service integrates with external spam detection services
 * and implements internal spam detection logic.
 */
class SpamPreventionService
{
    protected string $stopForumSpamApiUrl = 'https://api.stopforumspam.org/api';

    /**
     * Check an email address against StopForumSpam database.
     *
     * @param string $email Email to check
     * @return array Check results with confidence scores
     */
    public function checkEmailAgainstStopForumSpam(string $email): array
    {
        try {
            $response = Http::timeout(5)->get($this->stopForumSpamApiUrl, [
                'email' => $email,
                'json' => true,
            ]);

            if (!$response->successful()) {
                Log::warning('StopForumSpam API request failed', [
                    'email' => $email,
                    'status' => $response->status(),
                ]);
                return $this->unknownResult();
            }

            $data = $response->json();
            
            if (!isset($data['success']) || $data['success'] != 1) {
                return $this->unknownResult();
            }

            $emailData = $data['email'] ?? [];
            
            return [
                'is_spam' => $emailData['appears'] ?? false,
                'frequency' => $emailData['frequency'] ?? 0,
                'last_seen' => $emailData['lastseen'] ?? null,
                'confidence' => $this->calculateConfidence($emailData),
                'source' => 'stopforumspam',
            ];
        } catch (\Exception $e) {
            Log::error('StopForumSpam check failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return $this->unknownResult();
        }
    }

    /**
     * Check an IP address against StopForumSpam database.
     *
     * @param string $ip IP address to check
     * @return array Check results with confidence scores
     */
    public function checkIpAgainstStopForumSpam(string $ip): array
    {
        try {
            $response = Http::timeout(5)->get($this->stopForumSpamApiUrl, [
                'ip' => $ip,
                'json' => true,
            ]);

            if (!$response->successful()) {
                return $this->unknownResult();
            }

            $data = $response->json();
            
            if (!isset($data['success']) || $data['success'] != 1) {
                return $this->unknownResult();
            }

            $ipData = $data['ip'] ?? [];
            
            return [
                'is_spam' => $ipData['appears'] ?? false,
                'frequency' => $ipData['frequency'] ?? 0,
                'last_seen' => $ipData['lastseen'] ?? null,
                'confidence' => $this->calculateConfidence($ipData),
                'source' => 'stopforumspam',
            ];
        } catch (\Exception $e) {
            Log::error('StopForumSpam IP check failed', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
            return $this->unknownResult();
        }
    }

    /**
     * Scan multiple users for spam indicators.
     *
     * @param int $limit Number of users to scan
     * @param bool $newUsersOnly Only scan recently registered users
     * @return array Scan results with flagged users
     */
    public function scanUsersForSpam(int $limit = 100, bool $newUsersOnly = true): array
    {
        $query = User::query();
        
        if ($newUsersOnly) {
            $query->where('created_at', '>=', now()->subDays(30));
        }
        
        $users = $query->limit($limit)->get();
        $flagged = [];
        $scanned = 0;

        foreach ($users as $user) {
            $result = $this->checkUser($user);
            
            if ($result['is_suspicious']) {
                $flagged[] = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'reasons' => $result['reasons'],
                    'confidence' => $result['confidence'],
                ];
            }
            
            $scanned++;
        }

        return [
            'scanned' => $scanned,
            'flagged' => count($flagged),
            'users' => $flagged,
        ];
    }

    /**
     * Check a user for spam indicators.
     *
     * @param User $user User to check
     * @return array Check results
     */
    public function checkUser(User $user): array
    {
        $reasons = [];
        $confidence = 0;

        // Check email against StopForumSpam
        $emailCheck = $this->checkEmailAgainstStopForumSpam($user->email);
        if ($emailCheck['is_spam']) {
            $reasons[] = 'Email flagged by StopForumSpam';
            $confidence = max($confidence, $emailCheck['confidence']);
        }

        // Check for suspicious patterns
        $patternCheck = $this->checkSuspiciousPatterns($user);
        if ($patternCheck['is_suspicious']) {
            $reasons = array_merge($reasons, $patternCheck['reasons']);
            $confidence = max($confidence, $patternCheck['confidence']);
        }

        // Check user behavior
        $behaviorCheck = $this->checkUserBehavior($user);
        if ($behaviorCheck['is_suspicious']) {
            $reasons = array_merge($reasons, $behaviorCheck['reasons']);
            $confidence = max($confidence, $behaviorCheck['confidence']);
        }

        return [
            'is_suspicious' => !empty($reasons),
            'reasons' => $reasons,
            'confidence' => $confidence,
        ];
    }

    /**
     * Check for suspicious patterns in user data.
     *
     * @param User $user User to check
     * @return array Pattern check results
     */
    protected function checkSuspiciousPatterns(User $user): array
    {
        $reasons = [];
        $confidence = 0;

        // Check for disposable email domains
        $domain = substr($user->email, strpos($user->email, '@') + 1);
        if ($this->isDisposableEmailDomain($domain)) {
            $reasons[] = 'Uses disposable email domain';
            $confidence = max($confidence, 70);
        }

        // Check for suspicious username patterns
        if (preg_match('/^[a-z]+\d{4,}$/i', $user->name)) {
            $reasons[] = 'Username follows bot pattern';
            $confidence = max($confidence, 60);
        }

        // Check for URLs in bio/description
        if (isset($user->bio) && preg_match('/https?:\/\//', $user->bio)) {
            $linksCount = substr_count($user->bio, 'http');
            if ($linksCount > 2) {
                $reasons[] = 'Multiple URLs in bio';
                $confidence = max($confidence, 50);
            }
        }

        return [
            'is_suspicious' => !empty($reasons),
            'reasons' => $reasons,
            'confidence' => $confidence,
        ];
    }

    /**
     * Check user behavior for spam indicators.
     *
     * @param User $user User to check
     * @return array Behavior check results
     */
    protected function checkUserBehavior(User $user): array
    {
        $reasons = [];
        $confidence = 0;

        // Check for rapid content creation
        if (method_exists($user, 'posts')) {
            $recentPostsCount = $user->posts()
                ->where('created_at', '>=', now()->subHours(1))
                ->count();
            
            if ($recentPostsCount > 10) {
                $reasons[] = 'Rapid content creation';
                $confidence = max($confidence, 80);
            }
        }

        // Check for low trust score
        $trustBalance = $user->trustBalances()->sum('balance');
        if ($trustBalance < -50) {
            $reasons[] = 'Very low trust score';
            $confidence = max($confidence, 70);
        }

        return [
            'is_suspicious' => !empty($reasons),
            'reasons' => $reasons,
            'confidence' => $confidence,
        ];
    }

    /**
     * Check if an email domain is known to be disposable.
     *
     * @param string $domain Domain to check
     * @return bool
     */
    protected function isDisposableEmailDomain(string $domain): bool
    {
        // This is a simplified list - in production, use a comprehensive database
        $disposableDomains = [
            'mailinator.com',
            'guerrillamail.com',
            '10minutemail.com',
            'tempmail.com',
            'throwaway.email',
            'yopmail.com',
            'maildrop.cc',
        ];

        return in_array($domain, $disposableDomains);
    }

    /**
     * Calculate confidence score from StopForumSpam data.
     *
     * @param array $data StopForumSpam response data
     * @return int Confidence percentage (0-100)
     */
    protected function calculateConfidence(array $data): int
    {
        if (!($data['appears'] ?? false)) {
            return 0;
        }

        $frequency = $data['frequency'] ?? 0;
        $lastSeen = $data['lastseen'] ?? null;

        // Base confidence on frequency
        $confidence = min($frequency * 10, 90);

        // Boost if recently seen
        if ($lastSeen) {
            $daysSince = now()->diffInDays($lastSeen);
            if ($daysSince < 7) {
                $confidence = min($confidence + 20, 100);
            } elseif ($daysSince < 30) {
                $confidence = min($confidence + 10, 100);
            }
        }

        return $confidence;
    }

    /**
     * Return unknown result structure.
     *
     * @return array
     */
    protected function unknownResult(): array
    {
        return [
            'is_spam' => false,
            'frequency' => 0,
            'last_seen' => null,
            'confidence' => 0,
            'source' => 'unknown',
        ];
    }
}