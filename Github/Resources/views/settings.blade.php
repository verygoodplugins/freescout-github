<div class="col-xs-12">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <form class="form-horizontal margin-top" method="POST" action="{{ route('github.save_settings') }}">
        {{ csrf_field() }}

        <!-- GitHub API Configuration -->
        <div class="form-group{{ $errors->has('github.token') ? ' has-error' : '' }}">
            <label for="github_token" class="col-sm-2 control-label">{{ __('GitHub Token') }}</label>
            <div class="col-sm-6">
                <input type="password" class="form-control" name="settings[github.token]" id="github_token" value="{{ old('settings.github.token', \Option::get('github.token')) }}" placeholder="{{ __('Personal Access Token or GitHub App Token') }}">
                @include('partials/field_error', ['field'=>'github.token'])
                <p class="form-help">
                    {{ __('Create a') }} 
                    <a href="https://github.com/settings/tokens" target="_blank">{{ __('Personal Access Token') }}</a>
                    {{ __('with repo and webhook permissions.') }}
                    <br><strong>{{ __('Recommendation:') }}</strong> {{ __('Use a Classic token for better organization repository access. Fine-grained tokens have limited organization support.') }}
                </p>
                <!-- Connection test result area -->
                <div id="github-connection-result" class="margin-top-10" style="display: none;">
                    <div class="alert" role="alert">
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <div class="github-connection-message"></div>
                    </div>
                </div>
            </div>
            <div class="col-sm-2">
                <button type="button" class="btn btn-default" id="test-connection">
                    <i class="fa fa-check-circle"></i> {{ __('Test Connection') }}
                </button>
            </div>
        </div>

        <div class="form-group{{ $errors->has('github.default_repository') ? ' has-error' : '' }}">
            <label for="github_default_repository" class="col-sm-2 control-label">{{ __('Default Repository') }}</label>
            <div class="col-sm-6">
                <select class="form-control" name="settings[github.default_repository]" id="github_default_repository">
                    <option value="">{{ __('Select Repository') }}</option>
                    @php
                        $current_repo = old('settings.github.default_repository', \Option::get('github.default_repository'));
                    @endphp
                    @if($current_repo)
                        <option value="{{ $current_repo }}" selected>{{ $current_repo }}</option>
                    @endif
                </select>
                @include('partials/field_error', ['field'=>'github.default_repository'])
                <p class="form-help">
                    {{ __('Default repository for creating issues') }}
                </p>
                <div id="github-repositories-loading" class="text-muted" style="display: none;">
                    <i class="fa fa-spinner fa-spin"></i> {{ __('Loading repositories...') }}
                </div>
            </div>
            <div class="col-sm-2">
                <button type="button" class="btn btn-default" id="refresh-repositories">
                    <i class="fa fa-refresh"></i> {{ __('Refresh') }}
                </button>
            </div>
        </div>

        <!-- Webhook Configuration -->
        <div class="form-group{{ $errors->has('github.webhook_secret') ? ' has-error' : '' }}">
            <label for="github_webhook_secret" class="col-sm-2 control-label">{{ __('Webhook Secret') }}</label>
            <div class="col-sm-6">
                <input type="password" class="form-control" name="settings[github.webhook_secret]" id="github_webhook_secret" value="{{ old('settings.github.webhook_secret', \Option::get('github.webhook_secret')) }}" placeholder="{{ __('Optional webhook secret for security') }}">
                @include('partials/field_error', ['field'=>'github.webhook_secret'])
                <p class="form-help">
                    {{ __('Webhook URL:') }} <code>{{ url('/github/webhook') }}</code>
                </p>
            </div>
        </div>

        <div class="form-group{{ $errors->has('github.organizations') ? ' has-error' : '' }}">
            <label for="github_organizations" class="col-sm-2 control-label">{{ __('Organizations') }}</label>
            <div class="col-sm-6">
                <input type="text" class="form-control" name="settings[github.organizations]" id="github_organizations" value="{{ old('settings.github.organizations', \Option::get('github.organizations')) }}" placeholder="{{ __('e.g., verygoodplugins, mycompany') }}">
                @include('partials/field_error', ['field'=>'github.organizations'])
                <p class="form-help">
                    {{ __('Comma-separated list of organization names to fetch repositories from. Required for fine-grained tokens.') }}
                </p>
            </div>
        </div>

        <!-- AI Configuration -->
        <div class="form-group{{ $errors->has('github.ai_service') ? ' has-error' : '' }}">
            <label for="github_ai_service" class="col-sm-2 control-label">{{ __('AI Service') }}</label>
            <div class="col-sm-6">
                <select class="form-control" name="settings[github.ai_service]" id="github_ai_service">
                    <option value="">{{ __('Disabled') }}</option>
                    <option value="openai" {{ old('settings.github.ai_service', \Option::get('github.ai_service')) == 'openai' ? 'selected' : '' }}>OpenAI</option>
                    <option value="claude" {{ old('settings.github.ai_service', \Option::get('github.ai_service')) == 'claude' ? 'selected' : '' }}>Claude</option>
                </select>
                @include('partials/field_error', ['field'=>'github.ai_service'])
                <p class="form-help">
                    {{ __('AI service for generating issue content and assigning labels') }}
                </p>
            </div>
        </div>

        <div class="form-group{{ $errors->has('github.ai_api_key') ? ' has-error' : '' }}">
            <label for="github_ai_api_key" class="col-sm-2 control-label">{{ __('AI API Key') }}</label>
            <div class="col-sm-6">
                <input type="password" class="form-control" name="settings[github.ai_api_key]" id="github_ai_api_key" value="{{ old('settings.github.ai_api_key', \Option::get('github.ai_api_key')) }}" placeholder="{{ __('API key for selected AI service') }}">
                @include('partials/field_error', ['field'=>'github.ai_api_key'])
                <p class="form-help">
                    {{ __('API key for the selected AI service (OpenAI or Claude)') }}
                </p>
            </div>
        </div>

        <!-- Feature Toggles -->
        <div class="form-group">
            <label for="github_create_remote_link" class="col-sm-2 control-label">{{ __('Create Remote Links') }}</label>
            <div class="col-sm-6">
                <div class="controls">
                    <label class="control-label">
                        <input type="checkbox" name="settings[github.create_remote_link]" value="1" {{ old('settings.github.create_remote_link', \Option::get('github.create_remote_link', true)) ? 'checked' : '' }}> {{ __('Add FreeScout links to GitHub issues') }}
                    </label>
                </div>
                <p class="form-help">
                    {{ __('Automatically add links back to FreeScout conversations in GitHub issues') }}
                </p>
            </div>
        </div>

        <div class="form-group">
            <label for="github_sync_status" class="col-sm-2 control-label">{{ __('Sync Status') }}</label>
            <div class="col-sm-6">
                <div class="controls">
                    <label class="control-label">
                        <input type="checkbox" name="settings[github.sync_status]" value="1" {{ old('settings.github.sync_status', \Option::get('github.sync_status', true)) ? 'checked' : '' }}> {{ __('Sync GitHub issue status with conversations') }}
                    </label>
                </div>
                <p class="form-help">
                    {{ __('Automatically update conversation status when GitHub issues are closed/reopened') }}
                </p>
            </div>
        </div>

        <div class="form-group">
            <label for="github_auto_assign_labels" class="col-sm-2 control-label">{{ __('Auto-assign Labels') }}</label>
            <div class="col-sm-6">
                <div class="controls">
                    <label class="control-label">
                        <input type="checkbox" name="settings[github.auto_assign_labels]" value="1" {{ old('settings.github.auto_assign_labels', \Option::get('github.auto_assign_labels', true)) ? 'checked' : '' }}> {{ __('Automatically assign labels to new issues') }}
                    </label>
                </div>
                <p class="form-help">
                    {{ __('Use AI and tag mapping to automatically assign appropriate labels') }}
                </p>
            </div>
        </div>

        <!-- Label Mapping Section -->
        <div class="form-group" id="label-mapping-section" style="display: none;">
            <label class="col-sm-2 control-label">{{ __('Label Mappings') }}</label>
            <div class="col-sm-10">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            {{ __('FreeScout Tag → GitHub Label Mappings') }}
                        </h4>
                    </div>
                    <div class="panel-body">
                        <div id="label-mappings-container">
                            <p class="text-muted">{{ __('Select a repository to configure label mappings') }}</p>
                        </div>
                        <button type="button" class="btn btn-default btn-sm" id="add-label-mapping">
                            <i class="fa fa-plus"></i> {{ __('Add Mapping') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="col-sm-6 col-sm-offset-2">
                <button type="submit" class="btn btn-primary">
                    {{ __('Save') }}
                </button>
            </div>
        </div>
    </form>
</div>


