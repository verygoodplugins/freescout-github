<?php

namespace Modules\Github\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

// Module alias
define('GITHUB_MODULE', 'github');

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
        $this->loadRoutes();
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
        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', GITHUB_MODULE);
        
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path(GITHUB_MODULE . '.php'),
        ], 'config');
    }

    /**
     * Register module configuration
     */
    protected function registerConfig()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php', GITHUB_MODULE
        );
    }

    /**
     * Load module views
     */
    protected function loadViews()
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', GITHUB_MODULE);
    }

    /**
     * Load module routes
     */
    protected function loadRoutes()
    {
        $this->loadRoutesFrom(__DIR__ . '/../Http/routes.php');
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
        // Add module's CSS file to the application layout
        \Eventy::addFilter('stylesheets', function($styles) {
            $styles[] = \Module::getPublicPath(GITHUB_MODULE).'/css/module.css';
            return $styles;
        });
        
        // Add module's JS file to the application layout
        \Eventy::addFilter('javascripts', function($javascripts) {
            $javascripts[] = \Module::getPublicPath(GITHUB_MODULE).'/js/laroute.js';
            $javascripts[] = \Module::getPublicPath(GITHUB_MODULE).'/js/module.js';
            return $javascripts;
        });

        // Add item to settings sections
        \Eventy::addFilter('settings.sections', function($sections) {
            $sections[GITHUB_MODULE] = ['title' => __('GitHub'), 'icon' => 'github', 'order' => 500];
            return $sections;
        }, 30);

        // Section settings
        \Eventy::addFilter('settings.section_settings', function($settings, $section) {
            if ($section != GITHUB_MODULE) {
                return $settings;
            }
           
            $settings = [
                'github.token' => \Option::get('github.token'),
                'github.default_repository' => \Option::get('github.default_repository'),
                'github.webhook_secret' => \Option::get('github.webhook_secret'),
                'github.ai_service' => \Option::get('github.ai_service'),
                'github.ai_api_key' => \Option::get('github.ai_api_key'),
                'github.create_remote_link' => \Option::get('github.create_remote_link'),
                'github.sync_status' => \Option::get('github.sync_status'),
                'github.auto_assign_labels' => \Option::get('github.auto_assign_labels'),
            ];

            return $settings;
        }, 20, 2);

        // Section parameters
        \Eventy::addFilter('settings.section_params', function($params, $section) {
            if ($section != GITHUB_MODULE) {
                return $params;
            }

            // Don't test connection here - it causes errors on every settings load
            // Instead, let the test connection button handle this
            $params['template_vars'] = [
                'auth_error' => '',
                'repositories' => [],
            ];

            $params['settings'] = [
                'github.token' => ['env' => 'GITHUB_TOKEN'],
                'github.default_repository' => ['env' => 'GITHUB_DEFAULT_REPOSITORY'],
                'github.webhook_secret' => ['env' => 'GITHUB_WEBHOOK_SECRET'],
                'github.ai_service' => ['env' => 'GITHUB_AI_SERVICE'],
                'github.ai_api_key' => ['env' => 'GITHUB_AI_API_KEY'],
                'github.create_remote_link' => ['env' => 'GITHUB_CREATE_REMOTE_LINK'],
                'github.sync_status' => ['env' => 'GITHUB_SYNC_STATUS'],
                'github.auto_assign_labels' => ['env' => 'GITHUB_AUTO_ASSIGN_LABELS'],
            ];

            return $params;
        }, 20, 2);

        // Settings view name
        \Eventy::addFilter('settings.view', function($view, $section) {
            if ($section != GITHUB_MODULE) {
                return $view;
            } else {
                return GITHUB_MODULE . '::settings';
            }
        }, 20, 2);

        // Add GitHub sidebar to conversations
        \Eventy::addAction('conversation.after_prev_convs', function($customer, $conversation, $mailbox) {
            
            if (\Option::get('github.token') && $conversation) {
                try {
                    $issues = \Modules\Github\Entities\GithubIssue::conversationLinkedIssues($conversation->id);
                    echo \View::make(GITHUB_MODULE . '::partials.sidebar', [
                        'issues' => $issues,
                        'conversation' => $conversation,
                    ])->render();
                } catch (\Exception $e) {
                    // Log error but don't break the page
                    \Helper::logException($e, '[GitHub] Sidebar render error: ' . $e->getMessage());
                    // Also show a simple debug div
                    echo '<div class="sidebar-block"><div class="sidebar-block-header"><h3>GitHub Issues (Debug)</h3></div><div class="sidebar-block-content">Error: ' . htmlspecialchars($e->getMessage()) . '</div></div>';
                }
            }
        }, 12, 3);

        // Handle conversation status changes
        \Eventy::addAction('conversation.status_changed', function($conversation, $user, $changed_on_reply) {
            if (\Option::get('github.sync_status')) {
                try {
                    \Modules\Github\Services\GithubApiClient::syncConversationStatus($conversation);
                } catch (\Exception $e) {
                    \Log::error('GitHub: Failed to sync conversation status: ' . $e->getMessage());
                }
            }
        });

    }

    /**
     * Load module assets - use Eventy filters in registerHooks() instead
     */
    protected function loadAssets()
    {
        // Assets are loaded via Eventy filters in registerHooks()
    }
}