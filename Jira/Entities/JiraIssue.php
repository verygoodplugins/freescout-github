<?php

namespace Modules\Jira\Entities;

use Illuminate\Database\Eloquent\Model;
use Watson\Rememberable\Rememberable;

class JiraIssue extends Model
{
    public $timestamps = false;

    protected $fillable = [
    	'key', 'type', 'status', 'summary'
    ];

    public static function createOrUpdate($data)
    {
    	return \JiraIssue::updateOrCreate([
            'key' => $data['key'] ?? ''
        ], $data);
    }

    public function getTitle()
    {
        return $this->key.' - '.$this->summary;
    }

    public function getUrl()
    {
        return config('jira.base_url').'/browse/'.$this->key;
    }

    public function getTypeIcon()
    {
        $types = \Jira::getMeta('types');
        if (empty($types) || !isset($types[$this->type])) {
            return '';
        } else {
            return $types[$this->type]['iconUrl'] ?? '';
        }
    }

    public function getTypeName()
    {
        $types = \Jira::getMeta('types');
        if (empty($types) || !isset($types[$this->type])) {
            return '';
        } else {
            return $types[$this->type]['name'] ?? '';
        }
    }

    public function getStatusName()
    {
        $statuses = \Jira::getMeta('statuses');
        if (empty($statuses) || !isset($statuses[$this->status])) {
            return '';
        } else {
            return $statuses[$this->status]['name'] ?? '';
        }
    }

    public static function conversationLinkedIssues($conversation_id)
    {
        return \JiraIssue::leftJoin('jira_issue_conversation', function ($join) {
                $join->on('jira_issue_conversation.jira_issue_id', '=', 'jira_issues.id');
            })
            ->where('jira_issue_conversation.conversation_id', $conversation_id)
            ->get();
    }
}