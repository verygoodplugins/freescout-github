<?php

use Illuminate\Support\Facades\Route;

// GitHub module routes
Route::group([
    'middleware' => ['web', 'auth', 'roles'],
    'prefix' => \Helper::getSubdirectory(),
    'namespace' => 'Modules\Github\Http\Controllers'
], function () {
    
    // AJAX routes for GitHub operations
    Route::post('/github/search-issues', 'GithubController@searchIssues')->name('github.search_issues');
    Route::post('/github/create-issue', 'GithubController@createIssue')->name('github.create_issue');
    Route::post('/github/link-issue', 'GithubController@linkIssue')->name('github.link_issue');
    Route::post('/github/unlink-issue', 'GithubController@unlinkIssue')->name('github.unlink_issue');
    Route::get('/github/issue-details/{id}', 'GithubController@getIssueDetails')->name('github.issue_details');
    Route::post('/github/refresh-issue/{id}', 'GithubController@refreshIssue')->name('github.refresh_issue');
    Route::post('/github/refresh-conversation-issues', 'GithubController@refreshConversationIssues')->name('github.refresh_conversation_issues');
    Route::post('/github/generate-content', 'GithubController@generateContent')->name('github.generate_content');
    
    // Settings routes
    Route::post('/github/test-connection', 'GithubController@testConnection')->name('github.test_connection');
    Route::post('/github/repositories', 'GithubController@getRepositories')->name('github.repositories');
    Route::get('/github/labels/{repository}', 'GithubController@getLabels')->name('github.labels')->where('repository', '.*');
    Route::post('/github/save-settings', 'GithubController@saveSettings')->name('github.save_settings');
    
    // Label mapping routes
    Route::get('/github/label-mappings', 'GithubController@getLabelMappings')->name('github.label_mappings');
    Route::post('/github/label-mappings', 'GithubController@saveLabelMappings')->name('github.save_label_mappings');
    
});

// Public webhook route (no middleware - external access required)
Route::group([
    'prefix' => \Helper::getSubdirectory(),
    'namespace' => 'Modules\Github\Http\Controllers'
], function () {
    Route::post('/github/webhook', 'GithubController@webhook')->name('github.webhook');
});