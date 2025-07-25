<?php

namespace Modules\Jira\Http\Controllers;

use Modules\Jira\Entities\JiraIssueConversation;
use App\Conversation;
use App\Thread;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class JiraController extends Controller
{
    /**
     * Ajax controller.
     */
    public function ajax(Request $request)
    {
        $response = [
            'status' => 'error',
            'msg'    => '', // this is error message
        ];

        switch ($request->action) {

            case 'search':
                $q = str_replace('"', '\\"', $request->q ?? '');

                // https://developer.atlassian.com/server/jira/platform/jira-rest-api-example-query-issues-6291606/
                // First search by key.
                $api_response = \Jira::apiCall('search', [
                    'jql' => 'key = "'.$q.'"',
                    'maxResults' => 1,
                ], \Jira::API_METHOD_GET);

                if (empty($api_response['issues'])) {
                    $api_response = \Jira::apiCall('search', [
                        'jql' => 'summary ~ "'.$q.'" or description ~ "'.$q.'"',
                        'maxResults' => 25,
                    ], \Jira::API_METHOD_GET);
                }

                if (isset($api_response['status']) && $api_response['status'] == 'error') {
                    $response['msg'] = $api_response['message'] ?? '';
                } elseif (!empty($api_response['errorMessages'][0])) {
                    //$response['msg'] = $api_response['errorMessages'][0];
                    $response['issues'] = [];
                    $response['status'] = 'success';
                } elseif (isset($api_response['issues'])) {
                    $issues = [];
                    
                    $meta = \Jira::getMetas();

                    foreach ($api_response['issues'] as $issue) {
                        $issue_fields = $issue['fields'];
                        $issue_data = [
                            'issuetype' => [
                                'id' => $issue_fields['issuetype']['id'],
                                'name' => $issue_fields['issuetype']['name'],
                                'iconUrl' => $issue_fields['issuetype']['iconUrl'],
                            ],
                            'key' => $issue['key'],
                            'summary' => $issue_fields['summary'],
                            'status' => [],
                            'description' => $issue_fields['description'] ?? '',
                            'url' => preg_replace("#/rest/api/.*$#", '/browse/'.$issue['key'], $issue['self']),
                        ];

                        $description = $issue_data['description'];

                        $issue_data['description'] = $description['content'][0]['text'] ?? '';
                        if (!$issue_data['description'] && !empty($description['content']) && is_array($description['content'])) {
                            // Try to find a "text" content.
                            foreach ($description['content'] as $content_item) {
                                if (!empty($content_item['content'][0]['text'])) {
                                    $issue_data['description'] = $content_item['content'][0]['text'];
                                    break;
                                }
                            }
                        }

                        if (mb_strlen($issue_data['description']) > 100) {
                            $issue_data['description'] = mb_substr($issue_data['description'], 0, 100).'…';
                        }
                        // if (!empty($issue_fields['description']['content'][0]['content'][0]['text'])) {
                        //     $issue_data['description'] = mb_substr($issue_fields['description']['content'][0]['content'][0]['text'], 0, 100).'…';
                        // }
                        if (!empty($issue_fields['status'])) {
                            $issue_data['status'] = [
                                'id' => $issue_fields['status']['id'],
                                'name' => $issue_fields['status']['name'],
                                'iconUrl' => $issue_fields['status']['iconUrl'],
                            ];
                            $meta['statuses'] = $meta['statuses'] ?? [];
                            $meta['statuses'][$issue_fields['status']['id']] = [
                                'name' => $issue_fields['status']['name'],
                                //'iconUrl' => $issue_fields['status']['iconUrl'],  
                            ];
                        }
                        $issues[] = $issue_data;

                        $meta['types'] = $meta['types'] ?? [];
                        $meta['types'][$issue_fields['issuetype']['id']] = [
                            'name' => $issue_fields['issuetype']['name'],
                            'iconUrl' => $issue_fields['issuetype']['iconUrl'],
                        ];
                    }

                    // Save metas.
                    \Jira::setMetas($meta);

                    $response['issues'] = $issues;
                    $response['status'] = 'success';
                }
                break;

            case 'link':
                $user = auth()->user();

                $conversation = Conversation::find($request->conversation_id);

                if (!$conversation) {
                    $response['msg'] = __('Conversation not found');
                }
                if (!$response['msg'] && !$user->can('update', $conversation)) {
                    $response['msg'] = __('Not enough permissions');
                }

                if (empty($response['msg'])) {
                    try {
                        $jira_issue = \JiraIssue::createOrUpdate([
                            'key' => $request->issue_key,
                            'type' => $request->issue_type,
                            'status' => $request->issue_status,
                            'summary' => $request->issue_summary,
                        ]);
                    } catch (\Exception $e) {
                        \Helper::logException($e, '[Jira]');
                    }
                    if (empty($jira_issue)) {
                        $response['msg'] = __('Error occurred creating a Jira issue');
                    }
                    if (empty($response['msg'])) {
                        $jira_issue_conversation = new JiraIssueConversation();
                        $jira_issue_conversation->jira_issue_id = $jira_issue->id;
                        $jira_issue_conversation->conversation_id = $conversation->id;

                        try {
                            $jira_issue_conversation->save();
                        } catch (\Exception $e) {
                            
                        }
                        // Add weblink to the issue in Jira.
                        // https://developer.atlassian.com/server/jira/platform/jira-rest-api-for-remote-issue-links/
                        $api_response = \Jira::apiCall('issue/'.$request->issue_key.'/remotelink', [
                            'globalId' => \Jira::getGlobalId($conversation),
                            'object' => [
                                'url' => $conversation->url(),
                                'title' => 'FreeScout #'.$conversation->number.' - '.$conversation->subject,
                            ]
                        ]);

                        if (isset($api_response['status']) && $api_response['status'] == 'error') {
                            \Helper::log('jira_errors', 'Error occurred creating a web link for Jira issue via API: '.$api_response['message'] ?? '');
                        }

                        $response['status'] = 'success';
                    }
                }
                break;

            case 'create_issue':

                $user = auth()->user();

                $conversation = Conversation::find($request->conversation_id);

                if (!$conversation) {
                    $response['msg'] = __('Conversation not found');
                }
                if (!$response['msg'] && !$user->can('update', $conversation)) {
                    $response['msg'] = __('Not enough permissions');
                }

                // https://developer.atlassian.com/server/jira/platform/jira-rest-api-examples/#creating-an-issue-using-a-project-key-and-field-names
                $api_response = \Jira::apiCall('issue', [
                    'fields' => [
                        'project' => [
                            'id' => $request->project
                        ],
                        'summary' => $request->summary,
                        // https://community.atlassian.com/t5/Jira-questions/Getting-Error-quot-Operation-value-must-be-an-Atlassian-Document/qaq-p/1304733
                        //'description' => $request->description,
                        'description' => [
                            'type' => 'doc',
                            'version' => 1,
                            'content' => [
                                [
                                    "type" => "paragraph",
                                    "content" => [
                                        [
                                          "type" => "text",
                                          "text" => $request->description
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'issuetype' => [
                            'id' => $request->type
                        ]
                    ]
                ]);

                if (!empty($api_response['key'])) {
                    try {
                        $jira_issue = \JiraIssue::createOrUpdate([
                            'key' => $api_response['key'],
                            'type' => $request->type,
                            'status' => 0,
                            'summary' => $request->summary,
                        ]);
                    } catch (\Exception $e) {
                        \Helper::logException($e, '[Jira]');
                    }
                    if (empty($jira_issue)) {
                        $response['msg'] = __('Error occurred creating a Jira issue');
                    }
                    if (empty($response['msg'])) {
                        $jira_issue_conversation = new JiraIssueConversation();
                        $jira_issue_conversation->jira_issue_id = $jira_issue->id;
                        $jira_issue_conversation->conversation_id = $conversation->id;

                        try {
                            $jira_issue_conversation->save();
                        } catch (\Exception $e) {
                            
                        }
                        // Add weblink to the issue in Jira.
                        // https://developer.atlassian.com/server/jira/platform/jira-rest-api-for-remote-issue-links/
                        $api_response = \Jira::apiCall('issue/'.$api_response['key'].'/remotelink', [
                            'globalId' => \Jira::getGlobalId($conversation),
                            'object' => [
                                'url' => $conversation->url(),
                                'title' => 'FreeScout #'.$conversation->number.' - '.$conversation->subject,
                            ]
                        ]);

                        if (isset($api_response['status']) && $api_response['status'] == 'error') {
                            \Helper::log('jira_errors', 'Error occurred creating a web link for Jira issue via API: '.$api_response['message'] ?? '');
                        }

                        $response['status'] = 'success';
                    }
                } elseif (!empty($api_response['errorMessages'])) {
                    $response['msg'] = json_encode($api_response['errorMessages']);
                } elseif (!empty($api_response['errors'])) {
                    $response['msg'] = json_encode($api_response['errors']);
                }
                break;

            case 'unlink_issue':
                $user = auth()->user();

                $conversation = Conversation::find($request->conversation_id);

                if (!$conversation) {
                    $response['msg'] = __('Conversation not found');
                }
                if (!$response['msg'] && !$user->can('update', $conversation)) {
                    $response['msg'] = __('Not enough permissions');
                }

                if (empty($response['msg'])) {
                    JiraIssueConversation::where('conversation_id', $conversation->id)
                        ->where('jira_issue_id', $request->jira_issue_id)
                        ->delete();

                    $issue = \JiraIssue::find($request->jira_issue_id);

                    if ($issue) {
                        // Remove weblink from the issue in Jira.
                        // https://developer.atlassian.com/server/jira/platform/jira-rest-api-for-remote-issue-links/
                        $api_response = \Jira::apiCall('issue/'.$issue->key.'/remotelink', [
                            'globalId' => \Jira::getGlobalId($conversation)
                        ], \Jira::API_METHOD_DELETE);

                        if (isset($api_response['status']) && $api_response['status'] == 'error') {
                            \Helper::log('jira_errors', 'Error occurred removing a web link for Jira issue via API: '.$api_response['message'] ?? '');
                        }
                    }

                    $response['status'] = 'success';
                }
                break;

            default:
                $response['msg'] = 'Unknown action';
                break;
        }

        if ($response['status'] == 'error' && empty($response['msg'])) {
            $response['msg'] = 'Unknown error occured';
        }

        return \Response::json($response);
    }

    public function webhook(Request $request)
    {
        if ($request->webhookEvent == 'jira:issue_updated'
            && $request->issue_event_type_name == 'issue_generic'
        ) {
            $issue = \JiraIssue::where('key', $request->issue['key'])->first();
            if (!$issue) {
                return '';
            }
            // Update status if needed.
            if ($issue->status != (int)$request->issue['fields']['status']['id']) {
                $issue->status = $request->issue['fields']['status']['id'];
                $issue->save();

                $meta = \Jira::getMetas();
                $meta['statuses'] = $meta['statuses'] ?? [];
                if (!isset($meta['statuses'][$issue->status])) {
                    $meta['statuses'][$issue->status] = [
                        'name' => $request->issue['fields']['status']['name'] ?? ''
                    ];
                    \Jira::setMetas($meta);
                }
            }
            $resolved_statuses = explode(',', config('jira.resolved_status'));
            foreach ($resolved_statuses as $i => $resolved_status) {
                $resolved_statuses[$i] = trim(mb_strtolower($resolved_status));
            }
            if (in_array(mb_strtolower($request->issue['fields']['status']['name']), $resolved_statuses)) {
                // Find linked conversations.
                $conversation_ids = JiraIssueConversation::where('jira_issue_id', $issue->id)
                    ->distinct()
                    ->pluck('conversation_id');
                if (count($conversation_ids)) {
                    $conversations = Conversation::whereIn('id', $conversation_ids)->get();
                    $created_by_user_id = \Jira::getUser()->id;
                    foreach ($conversations as $conversation) {
                        // Add notes to conversations.
                        Thread::create($conversation, Thread::TYPE_NOTE, __('Jira issue :a_begin:issue_key:a_end was closed.', ['a_begin' => '<span><a href="'.$issue->getUrl().'" target="_blank">', 'a_end' => '</a>&nbsp;</span>', 'issue_key' => $issue->key]), [
                            //'user_id'       => $conversation->user_id,
                            'created_by_user_id' => $created_by_user_id,
                            'source_via'    => Thread::PERSON_USER,
                            'source_type'   => Thread::SOURCE_TYPE_WEB,
                        ]);
                        // Change conversation statuses if needed.
                        if (($conversation->status == Conversation::STATUS_CLOSED 
                                || $conversation->status == Conversation::STATUS_PENDING
                            )
                            && config('jira.on_resolve') == \Jira::ON_RESOLVE_ACTIVATE
                        ) {
                            $conversation->changeStatus(Conversation::STATUS_ACTIVE, \Jira::getUser());
                        }
                    }
                }
            }
        }

        return '';
    }

    /**
     * Ajax html.
     */
    public function ajaxHtml(Request $request)
    {
        switch ($request->action) {
            case 'link_issue':
                $projects = [];
                // https://developer.atlassian.com/server/jira/platform/jira-rest-api-examples/#discovering-project-and-issue-type-data
                // https://confluence.atlassian.com/jiracore/createmeta-rest-endpoint-to-be-removed-975040986.html
                //$api_response = \Jira::apiCall('issue/createmeta', [], \Jira::API_METHOD_GET);
                // if (!empty($api_response['projects'])) {
                //     $projects = $api_response['projects'];
                // }
                $api_response = \Jira::apiCall('project', [],\Jira::API_METHOD_GET);

                if (!empty($api_response)) {
                    $projects = $api_response;
                    $response['status'] = 'success';
                }

                // Get types for projects.
                foreach ($projects as $i => $project) {
                    $project_response = \Jira::apiCall('project/'.($project['id'] ?? '').'', [],\Jira::API_METHOD_GET);
                    if ($project_response && isset($project_response['issueTypes'])) {
                        $projects[$i]['issueTypes'] = $project_response['issueTypes'];
                    }
                }

                return view('jira::ajax_html/link_issue', [
                    'projects' => $projects,
                ]);
        }

        abort(404);
    }
}
