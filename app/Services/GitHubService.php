<?php

namespace App\Services;

use Github\Client as GitHubClient;
use Github\HttpClient\Builder;
use Http\Adapter\Guzzle7\Client as GuzzleAdapter;
use Illuminate\Support\Facades\Log;

class GitHubService
{
    private GitHubClient $client;

    public function __construct(?GitHubClient $client = null)
    {
        if ($client) {
            $this->client = $client;
        } else {
            $httpClientBuilder = new Builder(new GuzzleAdapter());
            $this->client = new GitHubClient($httpClientBuilder);
            
            $token = config('services.github.token');
            if ($token) {
                $this->client->authenticate($token, null, GitHubClient::AUTH_ACCESS_TOKEN);
            }
        }
    }

    public function createIssue(array $data): array
    {
        // Check if GitHub is configured
        $token = config('services.github.token');
        $owner = config('services.github.repository.owner');
        $repo = config('services.github.repository.name');

        if (!$token || !$owner || !$repo) {
            Log::warning('GitHub feedback submission failed: Missing configuration', [
                'has_token' => !empty($token),
                'has_owner' => !empty($owner),
                'has_repo' => !empty($repo),
                'title' => $data['title'] ?? 'Unknown',
                'user_id' => $data['user_id'] ?? null
            ]);

            return [
                'success' => false,
                'error' => 'GitHub feedback is not configured. Please contact an administrator.',
            ];
        }

        $issueData = [
            'title' => $data['title'],
            'body' => $this->formatIssueBody($data),
            'labels' => $this->getLabels($data),
        ];

        try {
            $response = $this->client->api('issue')->create($owner, $repo, $issueData);
            
            Log::info('GitHub issue created successfully', [
                'issue_number' => $response['number'],
                'title' => $response['title'],
                'user_id' => $data['user_id'] ?? null
            ]);

            return [
                'success' => true,
                'issue_number' => $response['number'],
                'url' => $response['html_url'],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create GitHub issue', [
                'error' => $e->getMessage(),
                'title' => $data['title'] ?? 'Unknown',
                'user_id' => $data['user_id'] ?? null
            ]);

            return [
                'success' => false,
                'error' => 'Unable to submit feedback at this time. Please try again later or contact support.',
            ];
        }
    }

    private function formatIssueBody(array $data): string
    {
        $body = $data['description'] . "\n\n";
        
        // Format details as a table
        $body .= "| Field | Value |\n";
        $body .= "|-------|-------|\n";
        
        if (!empty($data['user_id'])) {
            $body .= "| User ID | {$data['user_id']} |\n";
        }
        
        if (!empty($data['category'])) {
            $body .= "| Category | " . ucfirst($data['category']) . " |\n";
        }
        
        if (!empty($data['priority'])) {
            $body .= "| Priority | " . ucfirst($data['priority']) . " |\n";
        }
        
        if (!empty($data['page_url'])) {
            $body .= "| Page URL | {$data['page_url']} |\n";
        }
        
        if (!empty($data['browser_info'])) {
            $body .= "| Browser | {$data['browser_info']} |\n";
        }
        
        if (!empty($data['environment'])) {
            $body .= "| Environment | {$data['environment']} |\n";
        }
        $body .= "| Submitted at | " . now()->format('Y-m-d H:i:s T') . " |\n";

        return $body;
    }

    private function getLabels(array $data): array
    {
        $labels = ['feedback', 'triage'];
        
        if (!empty($data['category'])) {
            $labels[] = $data['category'];
        }
        
        if (!empty($data['priority']) && $data['priority'] === 'high') {
            $labels[] = 'high-priority';
        }

        return $labels;
    }
}