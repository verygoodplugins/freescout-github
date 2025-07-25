<?php

return [
    'name' => 'Jira',
    'base_url' => env('JIRA_BASE_URL', ''),
    'username' => env('JIRA_USERNAME', ''),
    'api_token' => env('JIRA_API_TOKEN', ''),
    'on_resolve' => env('JIRA_ON_RESOLVE', ''),
    'resolved_status' => env('JIRA_RESOLVED_STATUS', 'Done'),
];
