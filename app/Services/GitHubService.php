<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class GitHubService
{
    private string $token;
    private string $graphqlUrl = 'https://api.github.com/graphql';

    public function __construct()
    {
        $this->token = config('services.github.token');
        
        if (!$this->token) {
            throw new \Exception('GitHub token is required for GitHubService');
        }
    }

    private function executeGraphQLQuery(string $query, array $variables = []): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/vnd.github.v4+json',
        ])->post($this->graphqlUrl, [
            'query' => $query,
            'variables' => $variables,
        ]);

        if (!$response->successful()) {
            throw new \Exception('GraphQL request failed: ' . $response->body());
        }

        $body = $response->json();

        if (isset($body['errors'])) {
            throw new \Exception('GraphQL errors: ' . json_encode($body['errors']));
        }

        return $body['data'] ?? [];
    }

    private function getRepositoryId(string $owner, string $name): ?string
    {
        $query = '
            query GetRepository($owner: String!, $name: String!) {
                repository(owner: $owner, name: $name) {
                    id
                }
            }
        ';

        $result = $this->executeGraphQLQuery($query, [
            'owner' => $owner,
            'name' => $name,
        ]);

        return $result['repository']['id'] ?? null;
    }

    private function getCopilotUserId(string $owner, string $repo): ?string
    {
        $query = '
            query GetSuggestedActors($owner: String!, $name: String!) {
                repository(owner: $owner, name: $name) {
                    suggestedActors(capabilities: [CAN_BE_ASSIGNED], first: 100) {
                        nodes {
                            login
                            __typename
                            ... on Bot {
                                id
                            }
                        }
                    }
                }
            }
        ';

        try {
            $result = $this->executeGraphQLQuery($query, [
                'owner' => $owner,
                'name' => $repo,
            ]);

            $actors = $result['repository']['suggestedActors']['nodes'] ?? [];
            
            // Look for the Copilot coding agent
            foreach ($actors as $actor) {
                if ($actor['login'] === 'copilot-swe-agent' && $actor['__typename'] === 'Bot') {
                    Log::info('Found Copilot coding agent', [
                        'login' => $actor['login'],
                        'id' => $actor['id'],
                    ]);
                    return $actor['id'];
                }
            }

            Log::info('Copilot coding agent not found in suggested actors', [
                'actors_found' => array_map(fn($actor) => $actor['login'], $actors),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::warning('Could not query suggested actors for Copilot', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function getLabelIds(string $owner, string $repo, array $labelNames): array
    {
        if (empty($labelNames)) {
            return [];
        }

        $query = '
            query GetLabels($owner: String!, $name: String!) {
                repository(owner: $owner, name: $name) {
                    labels(first: 100) {
                        nodes {
                            id
                            name
                        }
                    }
                }
            }
        ';

        $result = $this->executeGraphQLQuery($query, [
            'owner' => $owner,
            'name' => $repo,
        ]);

        $labels = $result['repository']['labels']['nodes'] ?? [];
        $labelIds = [];

        foreach ($labelNames as $labelName) {
            foreach ($labels as $label) {
                if ($label['name'] === $labelName) {
                    $labelIds[] = $label['id'];
                    break;
                }
            }
        }

        return $labelIds;
    }

    public function createIssue(array $data): array
    {
        // Check if GitHub is configured
        $owner = config('services.github.repository.owner');
        $repo = config('services.github.repository.name');

        if (!$this->token || !$owner || !$repo) {
            Log::warning('GitHub feedback submission failed: Missing configuration', [
                'has_token' => !empty($this->token),
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

        try {
            // Get repository ID
            $repositoryId = $this->getRepositoryId($owner, $repo);
            if (!$repositoryId) {
                throw new \Exception('Repository not found: ' . $owner . '/' . $repo);
            }

            // Get label IDs
            $labelNames = $this->getLabels($data);
            $labelIds = $this->getLabelIds($owner, $repo, $labelNames);

            // Get Copilot user ID for auto-assignment (only for critical issues)
            $assigneeIds = [];
            $isCritical = ($data['priority'] ?? 'low') === 'critical';
            
            if ($isCritical) {
                $copilotUserId = $this->getCopilotUserId($owner, $repo);
                if ($copilotUserId) {
                    $assigneeIds[] = $copilotUserId;
                }
            }

            // Create issue using GraphQL mutation
            $mutation = '
                mutation CreateIssue($repositoryId: ID!, $title: String!, $body: String!, $labelIds: [ID!], $assigneeIds: [ID!]) {
                    createIssue(input: {
                        repositoryId: $repositoryId
                        title: $title
                        body: $body
                        labelIds: $labelIds
                        assigneeIds: $assigneeIds
                    }) {
                        issue {
                            id
                            number
                            title
                            url
                        }
                    }
                }
            ';

            $variables = [
                'repositoryId' => $repositoryId,
                'title' => $data['title'],
                'body' => $this->formatIssueBody($data),
                'labelIds' => $labelIds,
                'assigneeIds' => $assigneeIds,
            ];

            $result = $this->executeGraphQLQuery($mutation, $variables);
            $issue = $result['createIssue']['issue'];

            Log::info('GitHub issue created successfully', [
                'issue_number' => $issue['number'],
                'title' => $issue['title'],
                'user_id' => $data['user_id'] ?? null,
                'priority' => $data['priority'] ?? 'low',
                'assigned_to_copilot' => !empty($assigneeIds),
                'copilot_assignment_reason' => !empty($assigneeIds) ? 'critical_priority' : ($isCritical ? 'copilot_not_available' : 'non_critical_priority'),
            ]);

            return [
                'success' => true,
                'issue_number' => $issue['number'],
                'url' => $issue['url'],
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

        $labels[] = match ($data['priority'] ?? 'low') {
            'high' => 'high-priority',
            'critical' => 'critical-priority',
            default => 'low-priority',
        };

        return $labels;
    }
}
