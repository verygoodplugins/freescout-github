<?php

namespace Modules\Github\Services;

use Modules\Github\Entities\GithubIssue;
use Modules\Github\Entities\GithubLabelMapping;
use App\Conversation;

class GithubApiClient
{
    const API_METHOD_GET = 'GET';
    const API_METHOD_POST = 'POST';
    const API_METHOD_PUT = 'PUT';
    const API_METHOD_PATCH = 'PATCH';
    const API_METHOD_DELETE = 'DELETE';

    private static $base_url = 'https://api.github.com';
    private static $timeout = 30;
    private static $user_agent = 'FreeScout-Github-Module/1.0';

    /**
     * Make API call to GitHub
     */
    public static function apiCall($endpoint, $params = [], $method = self::API_METHOD_GET, $token = null)
    {
        if ($token === null) {
            $token = \Option::get('github.token');
        }
        
        if (empty($token)) {
            return [
                'status' => 'error',
                'message' => 'GitHub token not configured'
            ];
        }

        $url = self::$base_url . '/' . ltrim($endpoint, '/');
        
        $curl = curl_init();
        
        // Determine SSL settings based on environment
        $isLocalDev = in_array(config('app.env'), ['local', 'dev', 'development']) || 
                      strpos(config('app.url'), '.local') !== false ||
                      strpos(config('app.url'), 'localhost') !== false;
        
        // Basic cURL options
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::$timeout,
            CURLOPT_USERAGENT => self::$user_agent,
            CURLOPT_SSL_VERIFYPEER => !$isLocalDev,
            CURLOPT_SSL_VERIFYHOST => $isLocalDev ? 0 : 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: token ' . $token,
                'Accept: application/vnd.github.v3+json',
                'Content-Type: application/json'
            ]
        ]);

        // Handle different HTTP methods
        switch ($method) {
            case self::API_METHOD_POST:
                curl_setopt($curl, CURLOPT_POST, true);
                if (!empty($params)) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
                }
                break;
                
            case self::API_METHOD_PUT:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (!empty($params)) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
                }
                break;
                
            case self::API_METHOD_PATCH:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
                if (!empty($params)) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
                }
                break;
                
            case self::API_METHOD_DELETE:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
                
            default: // GET
                if (!empty($params)) {
                    $url .= '?' . http_build_query($params);
                    curl_setopt($curl, CURLOPT_URL, $url);
                }
                break;
        }

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        $errno = curl_errno($curl);

        curl_close($curl);

        if ($error) {
            \Helper::log('github_api_errors', 'cURL Error: ' . $error . ' (Code: ' . $errno . ')');
            return [
                'status' => 'error',
                'message' => 'Connection error: ' . $error
            ];
        }

        $data = json_decode($response, true);

        if ($http_code >= 200 && $http_code < 300) {
            return [
                'status' => 'success',
                'data' => $data,
                'http_code' => $http_code
            ];
        } else {
            $error_message = isset($data['message']) ? $data['message'] : 'Unknown error';
            
            \Helper::log('github_api_errors', 'HTTP ' . $http_code . ': ' . $error_message);
            
            return [
                'status' => 'error',
                'message' => $error_message,
                'http_code' => $http_code
            ];
        }
    }

    /**
     * Test connection to GitHub API
     */
    public static function testConnection($token = null)
    {
        $response = self::apiCall('user', [], self::API_METHOD_GET, $token);
        
        if ($response['status'] === 'success') {
            // Get repositories for successful connection
            $repositories = self::getRepositories();
            
            return [
                'status' => 'success',
                'message' => 'Connected to GitHub as: ' . $response['data']['login'],
                'data' => [
                    'user' => $response['data'],
                    'repositories' => $repositories['status'] === 'success' ? $repositories['data'] : []
                ]
            ];
        }
        
        return $response;
    }

    /**
     * Get user repositories from multiple sources
     */
    public static function getRepositories($per_page = 100)
    {
        $allRepos = collect();
        
        // 1. Get user's personal repositories
        $userRepos = self::getUserRepositories($per_page);
        if ($userRepos['status'] === 'success') {
            $allRepos = $allRepos->concat($userRepos['data']);
        }
        
        // 2. Get user's organization memberships and their repositories
        $orgRepos = self::getOrganizationRepositories($per_page);
        if ($orgRepos['status'] === 'success') {
            $allRepos = $allRepos->concat($orgRepos['data']);
        }
        
        // 3. Get repositories from installations (for GitHub Apps/fine-grained tokens)
        $installationRepos = self::getInstallationRepositories($per_page);
        if ($installationRepos['status'] === 'success') {
            $allRepos = $allRepos->concat($installationRepos['data']);
        }
        
        // Deduplicate by full_name and filter
        $uniqueRepos = $allRepos
            ->unique('full_name')
            ->values()
            ->map(function ($repo) {
                return [
                    'id' => $repo['id'],
                    'name' => $repo['name'],
                    'full_name' => $repo['full_name'],
                    'private' => $repo['private'],
                    'has_issues' => $repo['has_issues'],
                    'updated_at' => $repo['updated_at']
                ];
            })
            ->sortBy('full_name')
            ->values()
            ->toArray();
        
        return [
            'status' => 'success',
            'data' => $uniqueRepos
        ];
    }
    
    /**
     * Get user's personal repositories
     */
    private static function getUserRepositories($per_page)
    {
        return self::apiCall('user/repos', [
            'per_page' => $per_page,
            'sort' => 'full_name',
            'affiliation' => 'owner,collaborator'
        ]);
    }
    
    /**
     * Get repositories from user's organizations
     */
    private static function getOrganizationRepositories($per_page)
    {
        $allOrgRepos = collect();
        
        try {
            // Method 1: Try to get organizations and their repos (works with classic tokens)
            $orgsResponse = self::apiCall('user/orgs', ['per_page' => 100]);
            
            if ($orgsResponse['status'] === 'success') {
                // For each organization, get repositories
                foreach ($orgsResponse['data'] as $org) {
                    $orgLogin = $org['login'];
                    
                    try {
                        $orgReposResponse = self::apiCall("orgs/{$orgLogin}/repos", [
                            'per_page' => $per_page,
                            'type' => 'all',
                            'sort' => 'full_name'
                        ]);
                        
                        if ($orgReposResponse['status'] === 'success') {
                            $allOrgRepos = $allOrgRepos->concat($orgReposResponse['data']);
                        }
                    } catch (\Exception $e) {
                        \Helper::log('github_api_errors', "Failed to get repos for org {$orgLogin}: " . $e->getMessage());
                    }
                }
            } else {
                // Method 2: For fine-grained tokens, try specific organization repos directly
                // This works if the token has access to specific organizations
                $configuredOrgs = \Option::get('github.organizations', 'verygoodplugins');
                $knownOrgs = array_filter(array_map('trim', explode(',', $configuredOrgs)));
                
                if (empty($knownOrgs)) {
                    $knownOrgs = ['verygoodplugins']; // Fallback
                }
                
                foreach ($knownOrgs as $orgLogin) {
                    try {
                        $orgReposResponse = self::apiCall("orgs/{$orgLogin}/repos", [
                            'per_page' => $per_page,
                            'type' => 'all',
                            'sort' => 'full_name'
                        ]);
                        
                        if ($orgReposResponse['status'] === 'success') {
                            $allOrgRepos = $allOrgRepos->concat($orgReposResponse['data']);
                        }
                    } catch (\Exception $e) {
                        \Helper::log('github_api_errors', "Failed to get repos for configured org {$orgLogin}: " . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            \Helper::log('github_api_errors', "Failed to get organizations: " . $e->getMessage());
        }
        
        return [
            'status' => 'success',
            'data' => $allOrgRepos->toArray()
        ];
    }
    
    /**
     * Get repositories from GitHub App installations (for fine-grained tokens)
     */
    private static function getInstallationRepositories($per_page)
    {
        $allInstallationRepos = collect();
        
        try {
            // Get user installations
            $installationsResponse = self::apiCall('user/installations', ['per_page' => 100]);
            
            if ($installationsResponse['status'] !== 'success') {
                // This might fail for classic tokens, which is fine
                return ['status' => 'success', 'data' => []];
            }
            
            // For each installation, get accessible repositories
            foreach ($installationsResponse['data']['installations'] ?? [] as $installation) {
                $installationId = $installation['id'];
                
                try {
                    $reposResponse = self::apiCall("user/installations/{$installationId}/repositories", [
                        'per_page' => $per_page
                    ]);
                    
                    if ($reposResponse['status'] === 'success') {
                        $repos = $reposResponse['data']['repositories'] ?? $reposResponse['data'] ?? [];
                        $allInstallationRepos = $allInstallationRepos->concat($repos);
                    }
                } catch (\Exception $e) {
                    // Continue with other installations if one fails
                    \Helper::log('github_api_errors', "Failed to get repos for installation {$installationId}: " . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            \Helper::log('github_api_errors', "Failed to get installations: " . $e->getMessage());
        }
        
        return [
            'status' => 'success',
            'data' => $allInstallationRepos->toArray()
        ];
    }

    /**
     * Get a specific repository
     */
    public static function getRepository($repository)
    {
        $response = self::apiCall("repos/{$repository}");

        if ($response['status'] === 'success') {
            $repo = $response['data'];
            return [
                'status' => 'success',
                'data' => [
                    'id' => $repo['id'],
                    'name' => $repo['name'],
                    'full_name' => $repo['full_name'],
                    'private' => $repo['private'],
                    'has_issues' => $repo['has_issues'],
                    'updated_at' => $repo['updated_at']
                ]
            ];
        }

        return $response;
    }

    /**
     * Get repository labels
     */
    public static function getLabels($repository)
    {
        $response = self::apiCall("repos/{$repository}/labels");

        if ($response['status'] === 'success') {
            return [
                'status' => 'success',
                'data' => collect($response['data'])->map(function ($label) {
                    return [
                        'name' => $label['name'],
                        'color' => $label['color'],
                        'description' => $label['description'] ?? ''
                    ];
                })->toArray()
            ];
        }

        return $response;
    }

    /**
     * Search issues in repository
     */
    public static function searchIssues($repository, $query = '', $state = 'all', $per_page = 20)
    {
        // If empty query, return empty results to avoid searching all issues
        if (empty(trim($query))) {
            return [
                'status' => 'success',
                'data' => [],
                'total_count' => 0
            ];
        }
        
        $trimmedQuery = trim($query);
        
        // First, try searching by issue number if it looks like a number
        if (is_numeric($trimmedQuery)) {
            $search_query = "repo:{$repository} {$trimmedQuery}";
            if ($state !== 'all') {
                $search_query .= " state:{$state}";
            }
            
            
            $response = self::apiCall('search/issues', [
                'q' => $search_query,
                'per_page' => $per_page,
                'sort' => 'updated'
            ]);
            
            if ($response['status'] === 'success' && !empty($response['data']['items'])) {
                return self::formatSearchResponse($response);
            }
        }
        
        // Try multiple search strategies for better matching
        $searchStrategies = [
            // Strategy 1: Exact phrase search (quoted)
            "repo:{$repository} \"{$trimmedQuery}\" in:title,body",
            // Strategy 2: Individual words (unquoted for partial matching)
            "repo:{$repository} {$trimmedQuery} in:title,body",
            // Strategy 3: Wildcard search if query is short enough
            strlen($trimmedQuery) >= 3 ? "repo:{$repository} {$trimmedQuery}* in:title,body" : null
        ];
        
        // Remove null strategies
        $searchStrategies = array_filter($searchStrategies);
        
        $allResults = collect();
        
        foreach ($searchStrategies as $index => $search_query) {
            if ($state !== 'all') {
                $search_query .= " state:{$state}";
            }
            

            $response = self::apiCall('search/issues', [
                'q' => $search_query,
                'per_page' => $per_page,
                'sort' => 'updated'
            ]);
            
            if ($response['status'] === 'success' && !empty($response['data']['items'])) {
                $allResults = $allResults->concat($response['data']['items']);
                
                // If we got good results from the first strategy, we can stop
                if ($index === 0 && count($response['data']['items']) >= 5) {
                    break;
                }
            }
        }
        
        // Create a fake successful response with our combined results
        $combinedResponse = [
            'status' => 'success',
            'data' => [
                'items' => $allResults->unique('id')->take($per_page)->values()->toArray(),
                'total_count' => $allResults->unique('id')->count()
            ]
        ];

        return self::formatSearchResponse($combinedResponse);
    }
    
    /**
     * Format search response to consistent structure
     */
    private static function formatSearchResponse($response)
    {
        return [
            'status' => 'success',
            'data' => collect($response['data']['items'])->map(function ($issue) {
                return [
                    'id' => $issue['id'],
                    'number' => $issue['number'],
                    'title' => $issue['title'],
                    'state' => $issue['state'],
                    'labels' => collect($issue['labels'])->pluck('name')->toArray(),
                    'assignees' => collect($issue['assignees'])->pluck('login')->toArray(),
                    'html_url' => $issue['html_url'],
                    'created_at' => $issue['created_at'],
                    'updated_at' => $issue['updated_at']
                ];
            })->toArray(),
            'total_count' => $response['data']['total_count']
        ];
    }

    /**
     * Get specific issue
     */
    public static function getIssue($repository, $issue_number)
    {
        $response = self::apiCall("repos/{$repository}/issues/{$issue_number}");

        if ($response['status'] === 'success') {
            return [
                'status' => 'success',
                'data' => $response['data']
            ];
        }

        return $response;
    }

    /**
     * Create new issue
     */
    public static function createIssue($repository, $title, $body = '', $labels = [], $assignees = [])
    {
        $params = [
            'title' => $title,
            'body' => $body
        ];

        if (!empty($labels)) {
            $params['labels'] = $labels;
        }

        if (!empty($assignees)) {
            $params['assignees'] = $assignees;
        }

        $response = self::apiCall("repos/{$repository}/issues", $params, self::API_METHOD_POST);

        if ($response['status'] === 'success') {
            // Cache the issue locally
            $issue = GithubIssue::createOrUpdateFromGithub($response['data'], $repository);
            
            // Create remote link back to FreeScout if enabled
            if (\Option::get('github.create_remote_link', true)) {
                self::createRemoteLink($repository, $response['data']['number']);
            }

            return [
                'status' => 'success',
                'data' => $response['data'],
                'issue' => $issue
            ];
        }

        return $response;
    }

    /**
     * Update existing issue
     */
    public static function updateIssue($repository, $issue_number, $params)
    {
        $response = self::apiCall("repos/{$repository}/issues/{$issue_number}", $params, self::API_METHOD_PATCH);

        if ($response['status'] === 'success') {
            // Update local cache
            GithubIssue::createOrUpdateFromGithub($response['data'], $repository);
        }

        return $response;
    }

    /**
     * Create remote link in GitHub issue pointing back to FreeScout
     */
    private static function createRemoteLink($repository, $issue_number)
    {
        // This would require additional GitHub API calls or webhook setup
        // For now, we'll add a comment to the issue with the FreeScout link
        $freescout_url = url('/');
        $comment_body = "🔗 **FreeScout Link**: This issue was created from FreeScout support system.\n\n" .
                       "View original conversation: {$freescout_url}";

        self::apiCall("repos/{$repository}/issues/{$issue_number}/comments", [
            'body' => $comment_body
        ], self::API_METHOD_POST);
    }


    /**
     * Handle GitHub webhook
     */
    public static function handleWebhook($payload)
    {
        $event = $payload['action'] ?? '';
        $issue_data = $payload['issue'] ?? null;
        $repository = $payload['repository']['full_name'] ?? '';

        if (!$issue_data || !$repository) {
            return ['status' => 'error', 'message' => 'Invalid webhook payload'];
        }

        // Update or create local issue
        $issue = GithubIssue::createOrUpdateFromGithub($issue_data, $repository);

        // Handle status changes
        if (in_array($event, ['closed', 'reopened', 'opened'])) {
            foreach ($issue->conversations as $conversation) {
                // Just add the note - let humans decide the conversation status
                \App\Thread::create($conversation, \App\Thread::TYPE_NOTE, "GitHub issue #{$issue->number} was {$event}.", [
                    'created_by_user_id' => null,
                    'source_via' => \App\Thread::PERSON_SYSTEM
                ]);
            }
        }

        return ['status' => 'success', 'message' => 'Webhook processed'];
    }
}