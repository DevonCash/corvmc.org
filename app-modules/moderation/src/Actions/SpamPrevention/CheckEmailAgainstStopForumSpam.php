<?php

namespace CorvMC\Moderation\Actions\SpamPrevention;

use CorvMC\Moderation\Data\SpamCheckResultData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckEmailAgainstStopForumSpam
{
    use AsAction;

    /**
     * Check if email appears in StopForumSpam database
     *
     * @param  string  $email  Email address to check
     * @return SpamCheckResultData Result with spam status and details
     */
    public function handle(string $email): SpamCheckResultData
    {
        $config = config('services.stopforumspam');

        // Check if spam prevention is enabled
        if (! $config['enabled']) {
            return SpamCheckResultData::clean();
        }

        // Generate cache key from email hash
        $emailHash = md5(strtolower(trim($email)));
        $cacheKey = "stopforumspam:check:{$emailHash}";

        // Try to get cached result
        $cachedResult = Cache::get($cacheKey);
        if ($cachedResult !== null) {
            return $cachedResult;
        }

        try {
            // Call StopForumSpam API with MD5 hash for privacy
            $url = config('services.stopforumspam.api_url') . '?api_key=' . config('services.stopforumspam.api_key') . '&email=' . urlencode($email) . '&json';
            $response = Http::timeout(10)
                ->get($url);

            // Check if request was successful
            if (! $response->successful()) {
                Log::warning('StopForumSpam API request failed', [
                    'status' => $response->status(),
                    'email_hash' => $emailHash,
                ]);

                return SpamCheckResultData::error('API request failed');
            }

            $data = $response->json();

            // Verify API response structure
            if (! isset($data['success']) || $data['success'] != 1) {
                Log::warning('StopForumSpam API returned unsuccessful response', [
                    'response' => $data,
                    'email_hash' => $emailHash,
                ]);

                return SpamCheckResultData::error('Invalid API response');
            }

            // Create result from API response
            $result = SpamCheckResultData::fromApiResponse($data);

            // Cache the result
            Cache::put(
                $cacheKey,
                $result,
            );

            return $result;
        } catch (\Exception $e) {
            Log::error('StopForumSpam API check failed', [
                'email_hash' => $emailHash,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return error result - allows registration to proceed on API failure
            return SpamCheckResultData::error($e->getMessage());
        }
    }
}
