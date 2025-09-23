<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Invitation;
use App\Facades\UserInvitationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ImportUsersFromDenoKv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:users-from-deno-kv 
                            {--endpoint= : Deno KV REST API endpoint URL}
                            {--file= : Path to NDJSON export file from Deno KV}
                            {--key-prefix=users : Key prefix for user data in Deno KV}
                            {--dry-run : Show what would be imported without saving}
                            {--clean : Clean up test data first}
                            {--send-invites : Send invitation emails to imported users}
                            {--invitation-message= : Custom message for invitations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import users from Deno KV database via REST API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('👥 Importing Users from Deno KV');
        $this->line('=====================================');

        $endpoint = $this->option('endpoint');
        $file = $this->option('file');
        $keyPrefix = $this->option('key-prefix');
        $dryRun = $this->option('dry-run');
        $clean = $this->option('clean');
        $sendInvites = $this->option('send-invites');
        $invitationMessage = $this->option('invitation-message') ?: 'Welcome to Corvallis Music Collective! Please complete your registration to get started.';

        if ($dryRun) {
            $this->warn('🧪 DRY RUN MODE - No data will be saved');
        }

        if ($clean) {
            $this->info('🧹 Cleaning test data...');
            if (!$dryRun) {
                $this->cleanTestData();
            } else {
                $this->line('   → Would clean test data');
            }
        }

        try {
            $users = [];

            // Handle NDJSON file import
            if ($file) {
                if (!file_exists($file)) {
                    $this->error("❌ File not found: {$file}");
                    return 1;
                }
                $this->info("📁 Reading NDJSON file: {$file}");
                $users = $this->parseNdjsonFile($file, $keyPrefix);
            } 
            // Handle REST API import
            elseif ($endpoint) {
                if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
                    $this->error('❌ Invalid endpoint URL provided');
                    return 1;
                }
                $this->info("🔗 Connecting to: {$endpoint}");
                $users = $this->fetchUsersFromDenoKv($endpoint, $keyPrefix);
            }
            // Interactive mode
            else {
                $choice = $this->choice('Choose import method:', [
                    'file' => 'Import from NDJSON file',
                    'endpoint' => 'Import from REST API endpoint'
                ], 'file');

                if ($choice === 'file') {
                    $file = $this->ask('Enter path to NDJSON export file:');
                    if (!file_exists($file)) {
                        $this->error("❌ File not found: {$file}");
                        return 1;
                    }
                    $users = $this->parseNdjsonFile($file, $keyPrefix);
                } else {
                    $endpoint = $this->ask('Enter the Deno KV REST API endpoint URL:');
                    if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
                        $this->error('❌ Invalid endpoint URL provided');
                        return 1;
                    }
                    $users = $this->fetchUsersFromDenoKv($endpoint, $keyPrefix);
                }
            }

            $this->info("🔑 Key prefix: {$keyPrefix}");
            $this->newLine();
            
            if (empty($users)) {
                $this->warn('⚠️  No users found in Deno KV');
                return 0;
            }

            $this->info("📋 Found " . count($users) . " users to import");
            $this->newLine();

            $imported = 0;
            $invited = 0;
            $skipped = 0;
            $errors = [];

            foreach ($users as $userData) {
                try {
                    $result = $this->processUser($userData, $sendInvites, $invitationMessage, $dryRun);
                    
                    if ($result['status'] === 'imported') {
                        $this->info("✅ Created user: {$result['user']['name']} ({$result['user']['email']})");
                        $imported++;
                    } elseif ($result['status'] === 'invited') {
                        $this->info("📧 Invited: {$result['user']['name']} ({$result['user']['email']})");
                        $invited++;
                    } elseif ($result['status'] === 'skipped') {
                        $this->line("⏭️  Skipped: {$result['reason']}");
                        $skipped++;
                    }
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                    $this->error("❌ Error: {$e->getMessage()}");
                    $skipped++;
                }
            }

            $this->newLine();
            $this->info('📊 Import Summary:');
            $this->line("   ✅ Created users: {$imported}");
            $this->line("   📧 Sent invitations: {$invited}");
            $this->line("   ⏭️  Skipped: {$skipped}");
            
            if (!empty($errors)) {
                $this->line("   ❌ Errors: " . count($errors));
                foreach ($errors as $error) {
                    $this->line("      • {$error}");
                }
            }

            if ($dryRun) {
                $this->warn('👆 This was a dry run - no data was actually imported');
            } else {
                $this->info('✅ Import completed successfully!');
                if ($invited > 0) {
                    $this->info("📧 {$invited} invitation emails were sent");
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Import failed: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Fetch users from Deno KV via REST API.
     */
    private function fetchUsersFromDenoKv(string $endpoint, string $keyPrefix): array
    {
        $this->info('🔄 Fetching users from Deno KV...');

        // Try different common Deno KV REST API patterns
        $possiblePaths = [
            "/kv/{$keyPrefix}",
            "/api/kv/{$keyPrefix}",
            "/kv/list/{$keyPrefix}",
            "/api/users",
        ];

        foreach ($possiblePaths as $path) {
            $url = rtrim($endpoint, '/') . $path;
            
            try {
                $response = Http::timeout(30)->get($url);
                
                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Handle different response formats
                    if (isset($data['users']) && is_array($data['users'])) {
                        return $data['users'];
                    } elseif (isset($data['entries']) && is_array($data['entries'])) {
                        return array_map(fn($entry) => $entry['value'] ?? $entry, $data['entries']);
                    } elseif (is_array($data) && $this->isUserArray($data)) {
                        return $data;
                    }
                }
            } catch (\Exception $e) {
                $this->line("   → Tried {$url}: {$e->getMessage()}");
                continue;
            }
        }

        // If no standard endpoints work, ask for custom format
        $this->warn('⚠️  Standard endpoints not found. Provide custom data format:');
        $customData = $this->ask('Enter JSON data or file path containing user data:');

        if (file_exists($customData)) {
            $jsonData = file_get_contents($customData);
        } else {
            $jsonData = $customData;
        }

        $decoded = json_decode($jsonData, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON data provided');
        }

        return is_array($decoded) ? $decoded : [$decoded];
    }

    /**
     * Check if array contains user data.
     */
    private function isUserArray(array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $firstItem = reset($data);
        return is_array($firstItem) && 
               (isset($firstItem['email']) || isset($firstItem['name']));
    }

    /**
     * Process and validate user data.
     */
    private function processUser(array $userData, bool $sendInvites, string $invitationMessage, bool $dryRun): array
    {
        // Validate required fields
        $validator = Validator::make($userData, [
            'email' => 'required|email',
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new \Exception("Invalid user data: " . implode(', ', $validator->errors()->all()));
        }

        $email = $userData['email'];
        $name = $userData['name'];

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            return [
                'status' => 'skipped',
                'reason' => "User with email {$email} already exists"
            ];
        }

        // Check if invitation already exists
        $existingInvitation = Invitation::withoutGlobalScopes()
            ->where('email', $email)
            ->where('used_at', null)
            ->first();

        if ($existingInvitation) {
            return [
                'status' => 'skipped',
                'reason' => "Invitation already exists for email {$email}"
            ];
        }

        if ($dryRun) {
            return [
                'status' => $sendInvites ? 'invited' : 'imported',
                'user' => ['name' => $name, 'email' => $email]
            ];
        }

        if ($sendInvites) {
            // Create invitation directly for imports (system-generated)
            $invitation = Invitation::create([
                'inviter_id' => null, // System-generated invitation
                'email' => $email,
                'expires_at' => now()->addWeeks(2), // Longer expiry for imports
                'message' => $invitationMessage,
                'data' => [
                    'imported_from_deno_kv' => true,
                    'original_data' => [
                        'name' => $name,
                        'pronouns' => $userData['pronouns'] ?? null,
                        'deno_id' => $userData['deno_id'] ?? null,
                        'created_at' => $userData['created_at'] ?? null,
                        'trust_points' => $userData['trust_points'] ?? null,
                        'community_event_trust_points' => $userData['community_event_trust_points'] ?? 0,
                    ]
                ]
            ]);

            // Send the notification manually
            \Notification::route('mail', $email)
                ->notify(new \App\Notifications\UserInvitationNotification($invitation, [
                    'message' => $invitationMessage
                ]));

            $invitation->markAsSent();

            return [
                'status' => 'invited',
                'user' => ['name' => $name, 'email' => $email],
                'invitation' => $invitation
            ];
        } else {
            // Create user directly (original behavior)
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'pronouns' => $userData['pronouns'] ?? null,
                'password' => Hash::make(Str::random(32)), // Random password, user will set their own
                'email_verified_at' => isset($userData['email_verified']) && $userData['email_verified'] 
                    ? Carbon::now() 
                    : null,
                'trust_points' => $userData['trust_points'] ?? null,
                'community_event_trust_points' => $userData['community_event_trust_points'] ?? 0,
            ]);

            // Set created_at if provided
            if (isset($userData['created_at'])) {
                $user->created_at = Carbon::parse($userData['created_at']);
                $user->save();
            }

            return [
                'status' => 'imported',
                'user' => ['name' => $user->name, 'email' => $user->email]
            ];
        }
    }

    /**
     * Parse NDJSON file from Deno KV export.
     */
    private function parseNdjsonFile(string $filePath, string $keyPrefix): array
    {
        $this->info('🔄 Parsing NDJSON file...');
        
        $users = [];
        $userCount = 0;
        $totalLines = 0;
        
        $handle = fopen($filePath, 'r');
        
        while (($line = fgets($handle)) !== false) {
            $totalLines++;
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }
            
            try {
                $entry = json_decode($line, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->line("   ⚠️  Skipping invalid JSON on line {$totalLines}");
                    continue;
                }
                
                // Check if this is a user entry
                if ($this->isUserEntry($entry, $keyPrefix)) {
                    $userData = $this->extractUserData($entry);
                    if ($userData) {
                        $users[] = $userData;
                        $userCount++;
                        
                        if ($userCount % 10 === 0) {
                            $this->line("   📋 Found {$userCount} users so far...");
                        }
                    }
                }
                
            } catch (\Exception $e) {
                $this->line("   ⚠️  Error parsing line {$totalLines}: " . $e->getMessage());
                continue;
            }
        }
        
        fclose($handle);
        
        $this->info("✅ Parsed {$totalLines} total entries, found {$userCount} users");
        
        return $users;
    }

    /**
     * Check if NDJSON entry is a user record.
     */
    private function isUserEntry(array $entry, string $keyPrefix): bool
    {
        if (!isset($entry['key']) || !is_array($entry['key'])) {
            return false;
        }
        
        // Check if first key part matches our prefix
        $firstKey = $entry['key'][0] ?? null;
        
        return isset($firstKey['type']) && 
               $firstKey['type'] === 'string' && 
               isset($firstKey['value']) &&
               $firstKey['value'] === $keyPrefix;
    }

    /**
     * Extract user data from Deno KV typed entry.
     */
    private function extractUserData(array $entry): ?array
    {
        if (!isset($entry['value']['value']) || !is_array($entry['value']['value'])) {
            return null;
        }
        
        $valueData = $entry['value']['value'];
        $userData = [];
        
        // Extract typed values
        foreach ($valueData as $field => $typedValue) {
            if (!is_array($typedValue) || !isset($typedValue['type'])) {
                continue;
            }
            
            // Skip undefined values
            if ($typedValue['type'] === 'undefined') {
                continue;
            }
            
            // Extract the actual value
            if (isset($typedValue['value'])) {
                switch ($field) {
                    case 'id':
                        $userData['deno_id'] = $typedValue['value'];
                        break;
                    case 'email':
                        $userData['email'] = $typedValue['value'];
                        break;
                    case 'password':
                        // Note: This is likely a hash, we'll handle it specially
                        $userData['deno_password_hash'] = $typedValue['value'];
                        break;
                    case 'name':
                        $userData['name'] = $typedValue['value'];
                        break;
                    case 'pronouns':
                        $userData['pronouns'] = $typedValue['value'];
                        break;
                    case 'stripe_id':
                        if ($typedValue['type'] !== 'undefined') {
                            $userData['stripe_id'] = $typedValue['value'];
                        }
                        break;
                    case 'email_verified':
                        $userData['email_verified'] = $typedValue['value'];
                        break;
                    case 'created_at':
                        if ($typedValue['type'] === 'Date') {
                            $userData['created_at'] = $typedValue['value'];
                        }
                        break;
                    case 'trust_points':
                        if ($typedValue['type'] === 'object') {
                            $userData['trust_points'] = $typedValue['value'];
                        }
                        break;
                    case 'community_event_trust_points':
                        if (is_numeric($typedValue['value'])) {
                            $userData['community_event_trust_points'] = (int) $typedValue['value'];
                        }
                        break;
                }
            }
        }
        
        // Validate required fields
        if (empty($userData['email'])) {
            return null;
        }
        
        // Generate name from email if not provided
        if (empty($userData['name'])) {
            $userData['name'] = explode('@', $userData['email'])[0];
        }
        
        return $userData;
    }

    /**
     * Clean up test data.
     */
    private function cleanTestData(): void
    {
        $testEmails = [
            '%test%',
            '%demo%',
            '%example%',
            'test@%',
            'demo@%',
        ];

        foreach ($testEmails as $pattern) {
            $deleted = User::where('email', 'like', $pattern)->delete();
            if ($deleted > 0) {
                $this->line("   🗑️  Removed {$deleted} test users matching: {$pattern}");
            }
        }
    }
}
