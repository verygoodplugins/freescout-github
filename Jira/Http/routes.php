<?php

Route::group(['middleware' => 'web', 'prefix' => \Helper::getSubdirectory(), 'namespace' => 'Modules\Jira\Http\Controllers'], function()
{
    Route::get('/jira/ajax-html/{action}/{param?}', ['uses' => 'JiraController@ajaxHtml', 'middleware' => ['auth', 'roles'], 'roles' => ['user', 'admin']])->name('jira.ajax_html');
    Route::post('/jira/ajax', ['uses' => 'JiraController@ajax', 'middleware' => ['auth', 'roles'], 'roles' => ['user', 'admin'], 'laroute' => true])->name('jira.ajax');
});

Route::group(['middleware' => [\App\Http\Middleware\EncryptCookies::class, \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class, \Illuminate\Session\Middleware\StartSession::class, \App\Http\Middleware\HttpsRedirect::class], 'prefix' => \Helper::getSubdirectory(), 'namespace' => 'Modules\Jira\Http\Controllers'], function()
{
    Route::post('/jira/webhook', ['uses' => 'JiraController@webhook'])->name('jira.webhook');
});