<?php

namespace Modules\Jira\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\CustomFields\Entities\CustomField;

class JiraIssueConversation extends Model
{
    protected $table = 'jira_issue_conversation';
    
    public $timestamps = false;

    protected $fillable = [
    	'jira_issue_id', 'conversation_id'
    ];

    /**
     * Get user.
     */
    public function conversation()
    {
        return $this->belongsTo('App\Conversation');
    }
}
