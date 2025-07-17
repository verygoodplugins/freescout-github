<?php

use Illuminate\Support\Facades\Route;

// GitHub module routes
Route::group([
    'middleware' => ['web', 'auth'],
    'prefix' => \Helper::getSubdirectory(),
    'namespace' => 'Modules\Github\Http\Controllers'
], function () {
    
    // AJAX routes for GitHub operations
    Route::post('/github/search-issues', 'GithubController@searchIssues')->name('github.search_issues');
    Route::post('/github/create-issue', 'GithubController@createIssue')->name('github.create_issue');
    Route::post('/github/link-issue', 'GithubController@linkIssue')->name('github.link_issue');
    Route::post('/github/unlink-issue', 'GithubController@unlinkIssue')->name('github.unlink_issue');
    Route::get('/github/issue-details/{id}', 'GithubController@getIssueDetails')->name('github.issue_details');
    
    // Settings routes
    Route::post('/github/test-connection', 'GithubController@testConnection')->name('github.test_connection');
    Route::get('/github/repositories', 'GithubController@getRepositories')->name('github.repositories');
    Route::get('/github/labels/{repository}', 'GithubController@getLabels')->name('github.labels');
    
    // Label mapping routes
    Route::get('/github/label-mappings', 'GithubController@getLabelMappings')->name('github.label_mappings');
    Route::post('/github/label-mappings', 'GithubController@saveLabelMappings')->name('github.save_label_mappings');
    
});

// Public webhook route (no auth required)
Route::group([
    'middleware' => ['web'],
    'prefix' => \Helper::getSubdirectory(),
    'namespace' => 'Modules\Github\Http\Controllers'
], function () {
    Route::post('/github/webhook', 'GithubController@webhook')->name('github.webhook');
});