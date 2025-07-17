<?php

namespace Modules\Github\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class GithubServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadConfig();
        $this->loadViews();
        $this->loadMigrations();
        $this->registerHooks();
        $this->loadAssets();
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerConfig();
    }

    /**
     * Load module configuration
     */
    protected function loadConfig()
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('github.php'),
        ], 'config');
    }

    /**
     * Register module configuration
     */
    protected function registerConfig()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php', 'github'
        );
    }

    /**
     * Load module views
     */
    protected function loadViews()
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'github');
    }

    /**
     * Load module migrations
     */
    protected function loadMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }

    /**
     * Register FreeScout hooks
     */
    protected function registerHooks()
    {
        // Register settings section
        \Eventy::addFilter('settings.sections', function($sections) {
            $sections[GITHUB_MODULE] = [
                'title' => __('GitHub'),
                'icon' => 'github',
                'order' => 500
            ];
            return $sections;
        });

        // Register settings
        \Eventy::addFilter('settings.settings', function($settings) {
            $github_settings = config('github.settings');
            foreach ($github_settings as $key => $config) {
                $settings[$key] = $config;
            }
            return $settings;
        });

        // Add GitHub sidebar to conversations
        \Eventy::addAction('conversation.after_prev_convs', function($customer, $conversation, $mailbox) {
            if (\Option::get('github.token')) {
                $issues = \Modules\Github\Entities\GithubIssue::conversationLinkedIssues($conversation->id);
                echo \View::make('github::partials.sidebar', [
                    'issues' => $issues,
                    'conversation' => $conversation,
                ])->render();
            }
        });

        // Handle conversation status changes
        \Eventy::addAction('conversation.status_changed', function($conversation, $user, $changed_on_reply) {
            if (\Option::get('github.sync_status')) {
                \Modules\Github\Services\GithubApiClient::syncConversationStatus($conversation);
            }
        });

        // Add GitHub settings view
        \Eventy::addAction('settings.view', function($section) {
            if ($section == GITHUB_MODULE) {
                echo \View::make('github::settings')->render();
            }
        });
    }

    /**
     * Load module assets
     */
    protected function loadAssets()
    {
        // Register CSS
        \Eventy::addAction('layout.head', function() {
            echo '<link rel="stylesheet" href="' . \Module::asset('github:css/module.css') . '">';
        });

        // Register JavaScript
        \Eventy::addAction('layout.body_bottom', function() {
            echo '<script src="' . \Module::asset('github:js/module.js') . '"></script>';
        });
    }
}