<?php

use App\Services\GitHubService;
use Github\Client as GitHubClient;

it('formats issue body correctly', function () {
    $client = Mockery::mock(GitHubClient::class);
    $service = new GitHubService($client);
    
    // Use reflection to access the private method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('formatIssueBody');
    $method->setAccessible(true);
    
    $data = [
        'description' => 'This is a test issue',
        'user_id' => 123,
        'user_name' => 'Test User',
        'user_email' => 'test@example.com',
        'category' => 'bug',
        'priority' => 'high',
        'page_url' => 'https://example.com/page',
        'browser_info' => 'Chrome 91.0',
        'environment' => 'testing',
    ];
    
    $body = $method->invoke($service, $data);
    
    expect($body)
        ->toContain('This is a test issue')
        ->toContain('| User ID | 123 |')
        ->toContain('| Category | Bug |')
        ->toContain('| Priority | High |')
        ->toContain('| Page URL | https://example.com/page |')
        ->toContain('| Browser | Chrome 91.0 |')
        ->toContain('| Environment | testing |');
});

it('generates correct labels', function () {
    $client = Mockery::mock(GitHubClient::class);
    $service = new GitHubService($client);
    
    // Use reflection to access the private method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('getLabels');
    $method->setAccessible(true);
    
    $data = [
        'category' => 'feature',
        'priority' => 'high',
    ];
    
    $labels = $method->invoke($service, $data);
    
    expect($labels)
        ->toContain('feedback')
        ->toContain('triage')
        ->toContain('feature')
        ->toContain('high-priority');
});

it('generates basic labels for general feedback', function () {
    $client = Mockery::mock(GitHubClient::class);
    $service = new GitHubService($client);
    
    // Use reflection to access the private method
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('getLabels');
    $method->setAccessible(true);
    
    $data = [
        'category' => 'general',
        'priority' => 'medium',
    ];
    
    $labels = $method->invoke($service, $data);
    
    expect($labels)
        ->toContain('feedback')
        ->toContain('triage')
        ->toContain('general')
        ->not->toContain('high-priority');
});