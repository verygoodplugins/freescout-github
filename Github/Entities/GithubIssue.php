<?php

namespace Modules\Github\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Conversation;

class GithubIssue extends Model
{
    protected $fillable = [
        'number',
        'repository',
        'title',
        'body',
        'state',
        'labels',
        'assignees',
        'author',
        'github_created_at',
        'github_updated_at',
        'html_url'
    ];

    protected $casts = [
        'labels' => 'array',
        'assignees' => 'array',
        'github_created_at' => 'datetime',
        'github_updated_at' => 'datetime'
    ];

    /**
     * Get conversations linked to this issue
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'github_issue_conversation', 'github_issue_id', 'conversation_id');
    }

    /**
     * Get issues linked to a conversation
     */
    public static function conversationLinkedIssues($conversation_id)
    {
        return self::join('github_issue_conversation', 'github_issues.id', '=', 'github_issue_conversation.github_issue_id')
                   ->where('github_issue_conversation.conversation_id', $conversation_id)
                   ->select('github_issues.*')
                   ->get();
    }

    /**
     * Link issue to conversation
     */
    public function linkToConversation($conversation_id)
    {
        if (!$this->conversations()->where('conversation_id', $conversation_id)->exists()) {
            $this->conversations()->attach($conversation_id);
            return true;
        }
        return false;
    }

    /**
     * Unlink issue from conversation
     */
    public function unlinkFromConversation($conversation_id)
    {
        return $this->conversations()->detach($conversation_id);
    }

    /**
     * Create or update issue from GitHub data
     */
    public static function createOrUpdateFromGithub($github_data, $repository = null)
    {
        $repository = $repository ?: $github_data['repository']['full_name'];
        
        return self::updateOrCreate(
            [
                'number' => $github_data['number'],
                'repository' => $repository
            ],
            [
                'title' => $github_data['title'],
                'body' => $github_data['body'] ?? '',
                'state' => $github_data['state'],
                'labels' => collect($github_data['labels'])->pluck('name')->toArray(),
                'assignees' => collect($github_data['assignees'])->pluck('login')->toArray(),
                'author' => $github_data['user']['login'] ?? null,
                'github_created_at' => $github_data['created_at'],
                'github_updated_at' => $github_data['updated_at'],
                'html_url' => $github_data['html_url']
            ]
        );
    }

    /**
     * Get issue status badge class
     */
    public function getStatusBadgeClass()
    {
        switch ($this->state) {
            case 'open':
                return 'badge-success';
            case 'closed':
                return 'badge-secondary';
            default:
                return 'badge-light';
        }
    }

    /**
     * Get formatted labels for display
     */
    public function getFormattedLabels()
    {
        if (empty($this->labels)) {
            return [];
        }

        return collect($this->labels)->map(function ($label) {
            return [
                'name' => $label,
                'color' => $this->getLabelColor($label)
            ];
        })->toArray();
    }

    /**
     * Get label color (simplified color assignment)
     */
    private function getLabelColor($label)
    {
        $colors = [
            'bug' => '#d73a49',
            'enhancement' => '#a2eeef',
            'question' => '#d876e3',
            'help wanted' => '#008672',
            'good first issue' => '#7057ff',
            'documentation' => '#0075ca',
            'duplicate' => '#cfd3d7',
            'invalid' => '#e4e669',
            'wontfix' => '#ffffff'
        ];

        return $colors[strtolower($label)] ?? '#' . substr(md5($label), 0, 6);
    }

    /**
     * Get short repository name (without owner)
     */
    public function getShortRepository()
    {
        return explode('/', $this->repository)[1] ?? $this->repository;
    }

    /**
     * Check if issue is open
     */
    public function isOpen()
    {
        return $this->state === 'open';
    }

    /**
     * Check if issue is closed
     */
    public function isClosed()
    {
        return $this->state === 'closed';
    }
}