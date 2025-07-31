<?php

// GitHub Module Configuration
return [
    'name' => 'Github',
    'alias' => 'github',
    
    // GitHub API Configuration
    'api' => [
        'base_url' => 'https://api.github.com',
        'version' => 'v3',
        'timeout' => 30,
        'user_agent' => 'FreeScout-Github-Module/1.0'
    ],
    
    // Default settings
    'defaults' => [
        'repository' => '',
        'create_remote_link' => true,
        'ai_enabled' => true,
        'auto_assign_labels' => true
    ],
    
    // Settings configuration
    'settings' => [
        'github.token' => ['env' => 'GITHUB_TOKEN'],
        'github.default_repository' => ['env' => 'GITHUB_DEFAULT_REPOSITORY'],
        'github.webhook_secret' => ['env' => 'GITHUB_WEBHOOK_SECRET'],
        'github.ai_service' => ['env' => 'GITHUB_AI_SERVICE', 'default' => 'openai'],
        'github.ai_api_key' => ['env' => 'GITHUB_AI_API_KEY'],
        'github.create_remote_link' => ['default' => true],
        'github.auto_assign_labels' => ['default' => true]
    ]
];