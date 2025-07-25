<?php

namespace Modules\Github\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Modules\Github\Services\GithubApiClient;
use Modules\Github\Services\IssueContentGenerator;
use Modules\Github\Services\LabelAssignmentService;
use Modules\Github\Entities\GithubIssue;
use Modules\Github\Entities\GithubLabelMapping;
use App\Conversation;

class GithubController extends Controller
{
    /**
     * Test GitHub connection
     */
    public function testConnection(Request $request)
    {
        try {
            $token = $request->input('token');
            
            if (!$token) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'GitHub token is required'
                ]);
            }
            
            $result = GithubApiClient::testConnection($token);
            
            if ($result['status'] === 'success') {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'],
                    'repositories' => $result['data']['repositories'] ?? []
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message']
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Connection test failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get user repositories
     */
    public function getRepositories(Request $request)
    {
        $result = GithubApiClient::getRepositories();
        
        if ($result['status'] === 'success') {
            return response()->json([
                'status' => 'success',
                'repositories' => $result['data']
            ]);
        } else {
            return response()->json([
                'status' => 'error',
                'message' => $result['message']
            ]);
        }
    }

    /**
     * Get repository labels
     */
    public function getLabels(Request $request, $repository)
    {
        try {
            $repository = urldecode($repository);
            $result = GithubApiClient::getLabels($repository);
            
            if ($result['status'] === 'success') {
                return response()->json([
                    'status' => 'success',
                    'data' => $result['data'] ?? $result['labels'] ?? []
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'] ?? 'Failed to load labels'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load labels: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search GitHub issues
     */
    public function searchIssues(Request $request)
    {
        $request->validate([
            'repository' => 'required|string',
            'query' => 'nullable|string',
            'state' => 'nullable|string|in:open,closed,all',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        try {
            $result = GithubApiClient::searchIssues(
                $request->get('repository'),
                $request->get('query', ''),
                $request->get('state', 'open'),
                $request->get('per_page', 20)
            );

            if ($result['status'] === 'success') {
                return response()->json([
                    'status' => 'success',
                    'data' => $result['data'] ?? $result['issues'] ?? []
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'] ?? 'Failed to search issues'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to search issues: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get issue details
     */
    public function getIssueDetails(Request $request, $id)
    {
        $issue = GithubIssue::with('conversations')->find($id);
        
        if (!$issue) {
            return response()->json([
                'status' => 'error',
                'message' => 'Issue not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $issue
        ]);
    }

    /**
     * Create new GitHub issue
     */
    public function createIssue(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|integer|exists:conversations,id',
            'repository' => 'required|string',
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'labels' => 'nullable|array',
            'assignees' => 'nullable|array',
            'auto_generate_content' => 'nullable|boolean',
            'auto_assign_labels' => 'nullable|boolean'
        ]);

        $conversation = Conversation::findOrFail($request->get('conversation_id'));
        
        // Permission check
        if (!$conversation->userCanUpdate()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to create issues for this conversation'
            ], 403);
        }

        $repository = $request->get('repository');
        $title = $request->get('title');
        $body = $request->get('body');
        $labels = $request->get('labels', []);
        $assignees = $request->get('assignees', []);

        try {
            // Auto-generate content if requested
            if ($request->get('auto_generate_content', false) && (empty($title) || empty($body))) {
                $contentGenerator = new IssueContentGenerator();
                $generatedContent = $contentGenerator->generateContent($conversation);
                
                $title = $title ?: $generatedContent['title'];
                $body = $body ?: $generatedContent['body'];
            }

            // Auto-assign labels if requested
            if ($request->get('auto_assign_labels', false) && empty($labels)) {
                $labelService = new LabelAssignmentService();
                $repositoryLabels = GithubApiClient::getLabels($repository);
                
                if ($repositoryLabels['status'] === 'success') {
                    $assignedLabels = $labelService->assignLabels($conversation, $repositoryLabels['data']);
                    $labels = array_merge($labels, $assignedLabels);
                }
            }

            // Create the issue
            $result = GithubApiClient::createIssue($repository, $title, $body, $labels, $assignees);

            if ($result['status'] === 'success') {
                // Link the issue to the conversation
                $issue = $result['issue'];
                $issue->linkToConversation($conversation->id);

                // Add system note to conversation
                \App\Thread::create([
                    'conversation_id' => $conversation->id,
                    'type' => \App\Thread::TYPE_NOTE,
                    'body' => "GitHub issue created: <a href=\"{$result['data']['html_url']}\" target=\"_blank\">#{$result['data']['number']} {$result['data']['title']}</a>",
                    'created_by_user_id' => auth()->id(),
                    'source_via' => \App\Thread::PERSON_USER
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Issue created successfully',
                    'data' => $result['data'],
                    'issue' => $issue
                ]);
            } else {
                return response()->json($result, 400);
            }

        } catch (\Exception $e) {
            \Helper::logException($e, '[GitHub] Create Issue Error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while creating the issue'
            ], 500);
        }
    }

    /**
     * Link existing GitHub issue to conversation
     */
    public function linkIssue(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|integer|exists:conversations,id',
            'repository' => 'required|string',
            'issue_number' => 'required|integer'
        ]);

        $conversation = Conversation::findOrFail($request->get('conversation_id'));
        
        // Permission check
        if (!$conversation->userCanUpdate()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to link issues to this conversation'
            ], 403);
        }

        $repository = $request->get('repository');
        $issueNumber = $request->get('issue_number');

        try {
            // Get issue from GitHub
            $result = GithubApiClient::getIssue($repository, $issueNumber);

            if ($result['status'] === 'success') {
                // Create or update local issue
                $issue = GithubIssue::createOrUpdateFromGithub($result['data'], $repository);
                
                // Link to conversation
                $linked = $issue->linkToConversation($conversation->id);

                if ($linked) {
                    // Add system note to conversation
                    \App\Thread::create([
                        'conversation_id' => $conversation->id,
                        'type' => \App\Thread::TYPE_NOTE,
                        'body' => "GitHub issue linked: <a href=\"{$issue->html_url}\" target=\"_blank\">#{$issue->number} {$issue->title}</a>",
                        'created_by_user_id' => auth()->id(),
                        'source_via' => \App\Thread::PERSON_USER
                    ]);

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Issue linked successfully',
                        'issue' => $issue
                    ]);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Issue is already linked to this conversation'
                    ], 400);
                }
            } else {
                return response()->json($result, 400);
            }

        } catch (\Exception $e) {
            \Helper::logException($e, '[GitHub] Link Issue Error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while linking the issue'
            ], 500);
        }
    }

    /**
     * Unlink GitHub issue from conversation
     */
    public function unlinkIssue(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|integer|exists:conversations,id',
            'issue_id' => 'required|integer'
        ]);

        $conversation = Conversation::findOrFail($request->get('conversation_id'));
        
        // Permission check
        if (!$conversation->userCanUpdate()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to unlink issues from this conversation'
            ], 403);
        }

        $issue = GithubIssue::findOrFail($request->get('issue_id'));

        try {
            $unlinked = $issue->unlinkFromConversation($conversation->id);

            if ($unlinked) {
                // Add system note to conversation
                \App\Thread::create([
                    'conversation_id' => $conversation->id,
                    'type' => \App\Thread::TYPE_NOTE,
                    'body' => "GitHub issue unlinked: #{$issue->number} {$issue->title}",
                    'created_by_user_id' => auth()->id(),
                    'source_via' => \App\Thread::PERSON_USER
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Issue unlinked successfully'
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Issue is not linked to this conversation'
                ], 400);
            }

        } catch (\Exception $e) {
            \Helper::logException($e, '[GitHub] Unlink Issue Error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while unlinking the issue'
            ], 500);
        }
    }

    /**
     * Get label mappings for repository
     */
    public function getLabelMappings(Request $request)
    {
        $request->validate([
            'repository' => 'required|string'
        ]);

        $repository = $request->get('repository');
        $mappings = GithubLabelMapping::getRepositoryMappings($repository);

        return response()->json([
            'status' => 'success',
            'data' => $mappings
        ]);
    }

    /**
     * Save label mappings for repository
     */
    public function saveLabelMappings(Request $request)
    {
        $request->validate([
            'repository' => 'required|string',
            'mappings' => 'required|array',
            'mappings.*.freescout_tag' => 'required|string',
            'mappings.*.github_label' => 'required|string',
            'mappings.*.confidence_threshold' => 'nullable|numeric|min:0|max:1'
        ]);

        $repository = $request->get('repository');
        $mappings = $request->get('mappings');

        try {
            foreach ($mappings as $mapping) {
                GithubLabelMapping::createOrUpdateMapping(
                    $mapping['freescout_tag'],
                    $mapping['github_label'],
                    $repository,
                    $mapping['confidence_threshold'] ?? 0.80
                );
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Label mappings saved successfully'
            ]);

        } catch (\Exception $e) {
            \Helper::logException($e, '[GitHub] Save Label Mappings Error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while saving label mappings'
            ], 500);
        }
    }

    /**
     * Handle GitHub webhook
     */
    public function webhook(Request $request)
    {
        // Verify webhook signature if secret is configured
        $secret = \Option::get('github.webhook_secret');
        if ($secret) {
            $signature = $request->header('X-Hub-Signature-256');
            $payload = $request->getContent();
            $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
            
            if (!hash_equals($expectedSignature, $signature)) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $event = $request->header('X-GitHub-Event');
        $payload = $request->json()->all();

        try {
            // Handle different webhook events
            switch ($event) {
                case 'issues':
                    $result = GithubApiClient::handleWebhook($payload);
                    break;
                    
                case 'ping':
                    return response()->json(['message' => 'pong']);
                    
                default:
                    return response()->json(['message' => 'Event not handled'], 200);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            \Helper::logException($e, '[GitHub] Webhook Error');
            
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Save GitHub settings from settings page
     */
    public function saveSettings(Request $request)
    {
        $settings = $request->input('settings', []);
        $allowed = [
            'github.token',
            'github.default_repository',
            'github.webhook_secret',
            'github.organizations',
            'github.ai_service',
            'github.ai_api_key',
            'github.create_remote_link',
            'github.sync_status',
            'github.auto_assign_labels',
        ];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $settings)) {
                // Checkbox values: if not set, set to 0
                $value = $settings[$key];
                if (in_array($key, ['github.create_remote_link', 'github.sync_status', 'github.auto_assign_labels'])) {
                    $value = $value ? 1 : 0;
                }
                \Option::set($key, $value);
            } else if (in_array($key, ['github.create_remote_link', 'github.sync_status', 'github.auto_assign_labels'])) {
                // Unchecked checkboxes
                \Option::set($key, 0);
            }
        }
        return redirect()->back()->with('success', __('Settings saved.'));
    }
    
    /**
     * Generate AI content for issue
     */
    public function generateContent(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|integer|exists:conversations,id'
        ]);

        $conversation = Conversation::findOrFail($request->get('conversation_id'));
        
        // Permission check
        if (!$conversation->userCanUpdate()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to access this conversation'
            ], 403);
        }

        try {
            $contentGenerator = new IssueContentGenerator();
            $generatedContent = $contentGenerator->generateContent($conversation);
            
            return response()->json([
                'status' => 'success',
                'data' => $generatedContent
            ]);
        } catch (\Exception $e) {
            \Helper::logException($e, '[GitHub] Generate Content Error');
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate content: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Test token and show detailed API responses
     */
    public function testToken()
    {
        try {
            $results = [];
            
            // Test 1: Basic user info
            $userResponse = GithubApiClient::apiCall('user');
            $results['user'] = $userResponse;
            
            // Test 2: User repositories
            $userReposResponse = GithubApiClient::apiCall('user/repos', [
                'per_page' => 10,
                'type' => 'all'
            ]);
            $results['user_repos'] = [
                'status' => $userReposResponse['status'],
                'count' => $userReposResponse['status'] === 'success' ? count($userReposResponse['data']) : 0,
                'repos' => $userReposResponse['status'] === 'success' ? array_map(function($repo) {
                    return ['full_name' => $repo['full_name'], 'private' => $repo['private']];
                }, array_slice($userReposResponse['data'], 0, 5)) : []
            ];
            
            // Test 3: Organizations
            $orgsResponse = GithubApiClient::apiCall('user/orgs');
            $results['organizations'] = [
                'status' => $orgsResponse['status'],
                'count' => $orgsResponse['status'] === 'success' ? count($orgsResponse['data']) : 0,
                'orgs' => $orgsResponse['status'] === 'success' ? array_map(function($org) {
                    return $org['login'];
                }, $orgsResponse['data']) : []
            ];
            
            // Test 4: Specific org repos (verygoodplugins)
            $vgpReposResponse = GithubApiClient::apiCall('orgs/verygoodplugins/repos', [
                'per_page' => 10,
                'type' => 'all'
            ]);
            $results['verygoodplugins_repos'] = [
                'status' => $vgpReposResponse['status'],
                'message' => $vgpReposResponse['message'] ?? null,
                'count' => $vgpReposResponse['status'] === 'success' ? count($vgpReposResponse['data']) : 0,
                'repos' => $vgpReposResponse['status'] === 'success' ? array_map(function($repo) {
                    return ['full_name' => $repo['full_name'], 'private' => $repo['private']];
                }, array_slice($vgpReposResponse['data'], 0, 5)) : []
            ];
            
            // Test 5: Check specific repo
            $wpFusionResponse = GithubApiClient::apiCall('repos/verygoodplugins/wp-fusion');
            $results['wp_fusion_direct'] = [
                'status' => $wpFusionResponse['status'],
                'message' => $wpFusionResponse['message'] ?? null,
                'exists' => $wpFusionResponse['status'] === 'success',
                'private' => $wpFusionResponse['status'] === 'success' ? $wpFusionResponse['data']['private'] : null,
                'has_issues' => $wpFusionResponse['status'] === 'success' ? $wpFusionResponse['data']['has_issues'] : null
            ];
            
            return response()->json($results, 200, [], JSON_PRETTY_PRINT);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}