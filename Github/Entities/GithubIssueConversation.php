<?php

namespace Modules\Github\Entities;

use Illuminate\Database\Eloquent\Model;

class GithubIssueConversation extends Model
{
    protected $table = 'github_issue_conversation';
    
    public $timestamps = true;

    protected $fillable = [
        'github_issue_id', 'conversation_id'
    ];

    /**
     * Get the conversation
     */
    public function conversation()
    {
        return $this->belongsTo('App\Conversation');
    }

    /**
     * Get the GitHub issue
     */
    public function githubIssue()
    {
        return $this->belongsTo(GithubIssue::class, 'github_issue_id');
    }
}