<?php

namespace Modules\Jira\Providers;

use App\Conversation;
use App\User;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;

// Module alias
define('JIRA_MODULE', 'jira');

class JiraServiceProvider extends ServiceProvider
{
    // Jira.
    const ON_RESOLVE_ACTIVATE = 1;
    const ON_RESOLVE_NOTE = 2;

    const API_METHOD_GET = 'GET';
    const API_METHOD_POST = 'POST';
    const API_METHOD_DELETE = 'DELETE';

    const JIRA_USER_EMAIL = 'fsjira@example.org';

    public static $meta = null;

    public static $jira_user = null;

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerConfig();
        $this->registerViews();
        $this->registerFactories();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->hooks();
    }

    /**
     * Module hooks.
     */
    public function hooks()
    {
        // Add module's CSS file to the application layout.
        \Eventy::addFilter('stylesheets', function($styles) {
            $styles[] = \Module::getPublicPath(JIRA_MODULE).'/css/module.css';
            return $styles;
        });
        
        // Add module's JS file to the application layout.
        \Eventy::addFilter('javascripts', function($javascripts) {
            $javascripts[] = \Module::getPublicPath(JIRA_MODULE).'/js/laroute.js';
            $javascripts[] = \Module::getPublicPath(JIRA_MODULE).'/js/module.js';
            return $javascripts;
        });
        
        // Add item to settings sections.
        \Eventy::addFilter('settings.sections', function($sections) {
            $sections[JIRA_MODULE] = ['title' => __('Jira'), 'icon' => 'briefcase', 'order' => 500];

            return $sections;
        }, 30);

        // Section settings
        \Eventy::addFilter('settings.section_settings', function($settings, $section) {
           
            if ($section != JIRA_MODULE) {
                return $settings;
            }
           
            $settings['jira.base_url'] = config('jira.base_url');
            $settings['jira.username'] = config('jira.username');
            $settings['jira.api_token'] = config('jira.api_token');
            $settings['jira.on_resolve'] = config('jira.on_resolve');
            $settings['jira.resolved_status'] = config('jira.resolved_status');

            return $settings;
        }, 20, 2);

        // Section parameters.
        \Eventy::addFilter('settings.section_params', function($params, $section) {
           
            if ($section != JIRA_MODULE) {
                return $params;
            }

            $auth_error = '';
            // Get rooms and test API credentials.
            if (config('jira.base_url') 
                && config('jira.username') 
                && config('jira.api_token')
            ) {
                // Check credentials.
                $test_response = self::apiCall('project', [], self::API_METHOD_GET);

                if (!isset($test_response['status']) || $test_response['status'] != 'error') {
                    
                    \Option::set('jira.active', true);
                    $auth_error = '';

                } else {

                    \Option::set('jira.active', false);
                    if (!empty($test_response['message'])) {
                        $auth_error = $test_response['message'];
                    } else {
                        $auth_error = __('Unknown API error occurred.');
                    }
                }

            } elseif (\Option::get('jira.active')) {
                \Option::set('jira.active', false);
            }

            $params['template_vars'] = [
                'auth_error'       => $auth_error
            ];

            $params['settings'] = [
                'jira.base_url' => [
                    'env' => 'JIRA_BASE_URL',
                ],
                'jira.username' => [
                    'env' => 'JIRA_USERNAME',
                ],
                'jira.api_token' => [
                    'env' => 'JIRA_API_TOKEN',
                ],
                'jira.on_resolve' => [
                    'env' => 'JIRA_ON_RESOLVE',
                ],
                'jira.resolved_status' => [
                    'env' => 'JIRA_RESOLVED_STATUS',
                ],
            ];

            return $params;
        }, 20, 2);

        // Settings view name.
        \Eventy::addFilter('settings.view', function($view, $section) {
            if ($section != JIRA_MODULE) {
                return $view;
            } else {
                return 'jira::settings';
            }
        }, 20, 2);

        // After saving settings.
        \Eventy::addFilter('settings.after_save', function($response, $request, $section, $settings) {

            if ($section != JIRA_MODULE) {
                return $response;
            }

            $webhook_id = \Option::get('jira.webhook_id');

            // if (isset(\Option::$cache['jira.active'])) {
            //     unset(\Option::$cache['jira.active']);
            // }
            if (\Option::get('jira.active') && !$webhook_id) {
                // Try to set up a webhook.
                $api_response = \Jira::apiCall('webhook', [
                    'name' => 'FreeScout',
                    'url' => route('jira.webhook'),
                    'events' => [
                        'jira:issue_updated'
                    ],
                    'excludeBody' => false
                ]);

                if (!empty($api_response['messages'])) {
                    $message = json_encode($api_response['messages']);
                    $request->session()->flash('flash_error', __('Settings updated but could not set a we webhook in Jira').': '.$message);
                } elseif (!empty($api_response['self'])) {
                    $webhook_id = preg_replace("#.*/(\d+)$#", '$1', $api_response['self']);
                    if ($webhook_id) {
                        \Option::set('jira.webhook_id', $webhook_id);
                    }
                }
            } else if (!\Option::get('jira.active') && $webhook_id) {
                // Try to remove the webhook.
                $api_response = \Jira::apiCall('webhook/'.$webhook_id, [], self::API_METHOD_DELETE);

                if (empty($api_response['messages'])) {
                    \Option::remove('jira.webhook_id');
                }
            }

            return $response;
        }, 20, 4);

        // Sidebar.
        \Eventy::addAction('conversation.after_prev_convs', function($customer, $conversation, $mailbox) {
            $issues = \JiraIssue::select(['jira_issues.*'])
                ->leftJoin('jira_issue_conversation', function ($join) {
                    $join->on('jira_issue_conversation.jira_issue_id', '=', 'jira_issues.id');
                })
                ->where('jira_issue_conversation.conversation_id', $conversation->id)
                ->get();

            echo \View::make('jira::partials/sidebar', [
                'issues'         => $issues,
            ])->render();

        }, 12, 3);

        // Custom menu in conversation.
        \Eventy::addAction('conversation.customer.menu', function($customer, $conversation) {
            ?>
                <li role="presentation" class="col3-hidden"><a data-toggle="collapse" href=".jira-collapse-sidebar" tabindex="-1" role="menuitem">Jira</a></li>
            <?php
        }, 12, 2);

        // Conversation Status Updated
        \Eventy::addAction('conversation.status_changed', function($conversation, $user, $changed_on_reply) {
            // Mark the web link as closed in Jira.
            $linked_issues = \JiraIssue::conversationLinkedIssues($conversation->id);
            if (!count($linked_issues)) {
                return false;
            }
            $resolved = ($conversation->status == Conversation::STATUS_CLOSED);
            foreach ($linked_issues as $linked_issue) {
                // https://developer.atlassian.com/cloud/jira/platform/rest/v3/api-group-issue-remote-links/#api-rest-api-3-issue-issueidorkey-remotelink-post
                // We do it but Jira does not resolved Web Links as cross lined anymore.
                $api_response = \Jira::apiCall('issue/'.$linked_issue->key.'/remotelink', [
                    'globalId' => self::getGlobalId($conversation),
                    'object' => [
                        'url' => $conversation->url(),
                        'title' => 'FreeScout #'.$conversation->number.' - '.$conversation->subject,
                        'status' => [
                            'resolved' => $resolved
                        ]
                    ]
                ]);
                if (isset($api_response['status']) && $api_response['status'] == 'error') {
                    \Helper::log('jira_errors', 'Error occurred creating updating a web link for Jira issue via API: '.$api_response['message'] ?? '');
                }
            }
        }, 20, 3);
    }

    public static function getMetas()
    {
        if (self::$meta === null) {
            self::$meta = \Option::get('jira.meta');
        }
        if (!is_array(self::$meta)) {
            self::$meta = [];
        }
        return self::$meta;
    }

    public static function getMeta($key)
    {
        $meta = self::getMetas();

        return self::$meta[$key] ?? null;
    }

    public static function setMeta($key, $value)
    {
        $meta = self::getMetas();

        if (!is_array($meta)) {
            $meta = [];
        }
        $meta[$key] = $value;

        self::$meta = $meta;

        \Option::set('jira.meta', $meta);
    }

    public static function setMetas($meta)
    {
        self::$meta = $meta;

        \Option::set('jira.meta', $meta);
    }

    public static function getGlobalId($conversation)
    {
        return 'fs_'.crc32(config('app.key')).'_'.$conversation->id;
    }

    /**
     * https://docs.atlassian.com/software/jira/docs/api/REST/8.21.1/
     * https://developer.atlassian.com/cloud/jira/platform/rest/v3/intro/#about
     */
    public static function apiCall($method, $params, $http_method = self::API_METHOD_POST)
    {
        $response = [

        ];

        $api_path = '/rest/api/3/';

        if (preg_match("#^webhook#", $method)) {
            $api_path = '/rest/webhooks/1.0/';
        }

        $api_url = config('jira.base_url').$api_path.$method;
        if (($http_method == self::API_METHOD_GET || $http_method == self::API_METHOD_DELETE)
            && !empty($params)
        ) {
            $api_url .= '?'.http_build_query($params);
        }
        try {
            $ch = curl_init($api_url);

            $headers = [
                'Content-Type: application/json',
            ];

            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $http_method);
            curl_setopt($ch, CURLOPT_USERPWD, config('jira.username') . ":" . config('jira.api_token'));
            if ($http_method == self::API_METHOD_POST) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            \Helper::setCurlDefaultOptions($ch);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

            $json_response = curl_exec($ch);

            $response = json_decode($json_response, true);

            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                throw new \Exception(curl_error($ch), 1);
            }

            curl_close($ch);

            if (empty($response) && $status != 204) {
                throw new \Exception(__('Empty API response. Check your Jira credentials. HTTP status code: :status', ['status' => $status]), 1);
            } elseif ($status == 204) {
                return [
                    'status' => 'success',
                ];
            }

        } catch (\Exception $e) {
            \Helper::log('jira_errors', 'API error: '.$e->getMessage().'; Response: '.json_encode($response).'; Method: '.$method.'; Parameters: '.json_encode($params));

            return [
                'status' => 'error',
                'message' => __('API call error.').' '.$e->getMessage()
            ];
        }
        
        return $response;
    }

    /**
     * Get or create deleted user WorkfFlow.
     */
    public static function getUser()
    {
        if (!empty(self::$jira_user)) {
            return self::$jira_user;
        }
        self::$jira_user = User::where('email', self::JIRA_USER_EMAIL)->first();

        if (!self::$jira_user) {
            self::$jira_user = User::create([
                'first_name' => 'Jira',
                'last_name'  => '',
                'email'      => self::JIRA_USER_EMAIL,
                'password'   => bcrypt(\Str::random(25)),
                'status'     => User::STATUS_DELETED,
            ]);
        } else {
            // Set name if needed.
            // if (self::$jira_user->first_name != config('jira.user_full_name')) {
            //     self::$jira_user->first_name = config('jira.user_full_name');
            //     self::$jira_user->save();
            // }
        }

        return self::$jira_user;
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerTranslations();
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('jira.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'jira'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/jira');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath
        ],'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path . '/modules/jira';
        }, \Config::get('view.paths')), [$sourcePath]), 'jira');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $this->loadJsonTranslationsFrom(__DIR__ .'/../Resources/lang');
    }

    /**
     * Register an additional directory of factories.
     * @source https://github.com/sebastiaanluca/laravel-resource-flow/blob/develop/src/Modules/ModuleServiceProvider.php#L66
     */
    public function registerFactories()
    {
        if (! app()->environment('production')) {
            app(Factory::class)->load(__DIR__ . '/../Database/factories');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
