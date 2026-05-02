<?php

namespace CorvMC\SpaceManagement\Services;

use CorvMC\SpaceManagement\Models\Reservation;
use CorvMC\SpaceManagement\Settings\UltraloqSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UltraloqService
{
    protected const API_URL = 'https://api.u-tec.com/action';

    protected const OAUTH_URL = 'https://oauth.u-tec.com';

    /**
     * Minutes before reservation start that the lock code activates.
     */
    protected const EARLY_ACCESS_MINUTES = 15;

    /**
     * Minutes after reservation end that the lock code deactivates.
     */
    protected const LATE_ACCESS_MINUTES = 30;

    public function __construct(
        protected UltraloqSettings $settings,
    ) {}

    // =========================================================================
    // Lock User Management
    // =========================================================================

    /**
     * Create a temporary lock user for a reservation.
     *
     * Generates a random 6-digit code, creates a temporary user on the lock
     * with a time-limited schedule, and stores the code on the reservation.
     */
    public function createTemporaryUser(Reservation $reservation): ?string
    {
        if (! $this->settings->isConfigured()) {
            Log::warning('Ultraloq not configured, skipping lock code creation', [
                'reservation_id' => $reservation->id,
            ]);

            return null;
        }

        $code = $this->generateCode();
        $userName = $this->buildUserName($reservation);
        $schedule = $this->buildSchedule($reservation);

        $response = $this->sendCommand('st.lockUser', 'add', [
            'name' => $userName,
            'type' => 2, // Temporary User
            'password' => (int) $code,
            'daterange' => $schedule['daterange'],
            'weeks' => $schedule['weeks'],
            'timerange' => $schedule['timerange'],
        ]);

        if (! $response) {
            return null;
        }

        // Store the code on the reservation — the lock user ID comes back
        // asynchronously via deferred response, so we store the code now
        // and can look up the user by name later if needed.
        $reservation->update([
            'lock_code' => $code,
        ]);

        Log::info('Lock code created for reservation', [
            'reservation_id' => $reservation->id,
            'code' => $code,
            'schedule' => $schedule,
        ]);

        return $code;
    }

    /**
     * Delete the lock user for a reservation.
     */
    public function deleteUser(Reservation $reservation): bool
    {
        if (! $this->settings->isConfigured()) {
            return false;
        }

        if (! $reservation->ultraloq_user_id) {
            // Try to find by name if we don't have the ID
            $userId = $this->findUserIdByName($this->buildUserName($reservation));

            if (! $userId) {
                // No lock user to delete — clear the code and move on
                $reservation->update(['lock_code' => null]);

                return true;
            }

            $reservation->update(['ultraloq_user_id' => $userId]);
        }

        $response = $this->sendCommand('st.lockUser', 'delete', [
            'id' => $reservation->ultraloq_user_id,
        ]);

        $reservation->update([
            'lock_code' => null,
            'ultraloq_user_id' => null,
        ]);

        return (bool) $response;
    }

    /**
     * List all users on the configured lock.
     */
    public function listUsers(): ?array
    {
        $response = $this->sendCommand('st.lockUser', 'list');

        if (! $response) {
            return null;
        }

        $device = $response['payload']['devices'][0] ?? null;

        return $device['users'] ?? null;
    }

    /**
     * Find a lock user ID by name.
     */
    public function findUserIdByName(string $name): ?int
    {
        $users = $this->listUsers();

        if (! $users) {
            return null;
        }

        foreach ($users as $user) {
            if ($user['name'] === $name) {
                return $user['id'];
            }
        }

        return null;
    }

    // =========================================================================
    // Device Discovery
    // =========================================================================

    /**
     * Discover all devices on the connected account.
     *
     * @return array|null List of devices with id, name, category, etc.
     */
    public function discoverDevices(): ?array
    {
        $response = $this->sendRequest('Uhome.Device', 'Discovery', []);

        if (! $response) {
            return null;
        }

        return $response['payload']['devices'] ?? null;
    }

    // =========================================================================
    // OAuth
    // =========================================================================

    /**
     * Build the OAuth authorization URL.
     */
    public function getAuthorizationUrl(string $redirectUri, string $state): string
    {
        return self::OAUTH_URL.'/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->settings->client_id,
            'client_secret' => $this->settings->client_secret,
            'scope' => 'openapi',
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    /**
     * Exchange an authorization code for access and refresh tokens.
     */
    public function exchangeCode(string $code): bool
    {
        $response = Http::get(self::OAUTH_URL.'/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->settings->client_id,
            'client_secret' => $this->settings->client_secret,
            'code' => $code,
        ]);

        if (! $response->successful()) {
            Log::error('Ultraloq token exchange failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        $data = $response->json();

        $this->settings->access_token = $data['access_token'];
        $this->settings->refresh_token = $data['refresh_token'] ?? '';
        $this->settings->token_expires_at = isset($data['expires_in'])
            ? now()->addSeconds($data['expires_in'])->toDateTimeString()
            : null;
        $this->settings->save();

        return true;
    }

    /**
     * Refresh the access token using the refresh token.
     */
    public function refreshToken(): bool
    {
        if (! $this->settings->refresh_token) {
            return false;
        }

        $response = Http::get(self::OAUTH_URL.'/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->settings->client_id,
            'client_secret' => $this->settings->client_secret,
            'refresh_token' => $this->settings->refresh_token,
        ]);

        if (! $response->successful()) {
            Log::error('Ultraloq token refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        }

        $data = $response->json();

        $this->settings->access_token = $data['access_token'];
        if (isset($data['refresh_token'])) {
            $this->settings->refresh_token = $data['refresh_token'];
        }
        $this->settings->token_expires_at = isset($data['expires_in'])
            ? now()->addSeconds($data['expires_in'])->toDateTimeString()
            : null;
        $this->settings->save();

        return true;
    }

    // =========================================================================
    // SMS Message Composition
    // =========================================================================

    /**
     * Compose an SMS message for a reservation's lock code.
     */
    public static function composeSmsMessage(Reservation $reservation): ?string
    {
        if (! $reservation->lock_code) {
            return null;
        }

        $date = $reservation->reserved_at->format('M j');
        $startTime = $reservation->reserved_at
            ->subMinutes(self::EARLY_ACCESS_MINUTES)
            ->format('g:i A');
        $endTime = $reservation->reserved_until
            ->addMinutes(self::LATE_ACCESS_MINUTES)
            ->format('g:i A');

        return "Your practice space code is {$reservation->lock_code}. "
            ."Access from {$startTime} – {$endTime} on {$date}. "
            .'Enter code on the keypad to unlock.';
    }

    // =========================================================================
    // Internal Helpers
    // =========================================================================

    /**
     * Generate a random 6-digit numeric code.
     */
    public function generateCode(): string
    {
        return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Build a user name for the lock from a reservation.
     */
    protected function buildUserName(Reservation $reservation): string
    {
        $user = $reservation->reservable;
        $date = $reservation->reserved_at->format('n/j');

        if ($user && method_exists($user, 'getFilamentName')) {
            $name = $user->getFilamentName();

            return Str::limit($name, 15, '')." {$date}";
        }

        return "Res-{$reservation->id} {$date}";
    }

    /**
     * Build the temporary user schedule from a reservation.
     *
     * @return array{daterange: string[], weeks: int[], timerange: string[]}
     */
    public function buildSchedule(Reservation $reservation): array
    {
        $start = $reservation->reserved_at->copy()->subMinutes(self::EARLY_ACCESS_MINUTES);
        $end = $reservation->reserved_until->copy()->addMinutes(self::LATE_ACCESS_MINUTES);

        // If the access window spans midnight, extend daterange to cover both days
        $startDate = $start->format('Y-m-d');
        $endDate = $end->format('Y-m-d');

        return [
            'daterange' => [
                "{$startDate} {$start->format('H:i')}",
                "{$endDate} {$end->format('H:i')}",
            ],
            'weeks' => [0, 1, 2, 3, 4, 5, 6], // All days — daterange limits the actual dates
            'timerange' => [
                $start->format('H:i'),
                $end->format('H:i'),
            ],
        ];
    }

    /**
     * Send a device command to the configured lock.
     */
    protected function sendCommand(string $capability, string $name, array $arguments = []): ?array
    {
        $command = [
            'capability' => $capability,
            'name' => $name,
        ];

        if ($arguments) {
            $command['arguments'] = $arguments;
        }

        return $this->sendRequest('Uhome.Device', 'Command', [
            'devices' => [
                [
                    'id' => $this->settings->device_id,
                    'command' => $command,
                ],
            ],
        ]);
    }

    /**
     * Send a request to the Ultraloq API.
     */
    protected function sendRequest(string $namespace, string $name, array $payload): ?array
    {
        $this->ensureValidToken();

        $messageId = Str::uuid()->toString();

        $response = $this->httpClient()->post(self::API_URL, [
            'header' => [
                'namespace' => $namespace,
                'name' => $name,
                'messageId' => $messageId,
                'payloadVersion' => '1',
            ],
            'payload' => $payload,
        ]);

        if (! $response->successful()) {
            Log::error('Ultraloq API request failed', [
                'namespace' => $namespace,
                'name' => $name,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $data = $response->json();

        // Check for API-level errors in the device response
        $device = $data['payload']['devices'][0] ?? null;
        if ($device && isset($device['error'])) {
            Log::error('Ultraloq device error', [
                'namespace' => $namespace,
                'name' => $name,
                'error' => $device['error'],
            ]);

            return null;
        }

        return $data;
    }

    /**
     * Ensure the access token is valid, refreshing if needed.
     */
    protected function ensureValidToken(): void
    {
        if ($this->settings->isTokenExpired() && $this->settings->refresh_token) {
            $this->refreshToken();
        }
    }

    /**
     * Build an HTTP client with the bearer token.
     */
    protected function httpClient(): PendingRequest
    {
        return Http::withToken($this->settings->access_token)
            ->acceptJson()
            ->asJson();
    }
}
