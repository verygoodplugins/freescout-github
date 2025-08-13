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
                    <i class="glyphicon glyphicon-ok"></i> {{ __('Test Connection') }}
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
                    <i class="glyphicon glyphicon-refresh glyphicon-spin"></i> {{ __('Loading repositories...') }}
                </div>
            </div>
            <div class="col-sm-2">
                <button type="button" class="btn btn-default" id="refresh-repositories">
                    <i class="glyphicon glyphicon-refresh"></i> {{ __('Refresh') }}
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

        <div class="form-group{{ $errors->has('github.openai_model') ? ' has-error' : '' }}" id="openai_model_group" style="display: none;">
            <label for="github_openai_model" class="col-sm-2 control-label">{{ __('OpenAI Model') }}</label>
            <div class="col-sm-6">
                <select class="form-control" name="settings[github.openai_model]" id="github_openai_model">
                    <option value="gpt-5-mini" {{ old('settings.github.openai_model', \Option::get('github.openai_model', 'gpt-5-mini')) == 'gpt-5-mini' ? 'selected' : '' }}>GPT-5 Mini (Fast & Efficient)</option>
                    <option value="gpt-5" {{ old('settings.github.openai_model', \Option::get('github.openai_model', 'gpt-5-mini')) == 'gpt-5' ? 'selected' : '' }}>GPT-5 (Advanced)</option>
                    <option value="gpt-4-turbo-preview" {{ old('settings.github.openai_model', \Option::get('github.openai_model', 'gpt-5-mini')) == 'gpt-4-turbo-preview' ? 'selected' : '' }}>GPT-4 Turbo</option>
                    <option value="gpt-3.5-turbo" {{ old('settings.github.openai_model', \Option::get('github.openai_model', 'gpt-5-mini')) == 'gpt-3.5-turbo' ? 'selected' : '' }}>GPT-3.5 Turbo (Legacy)</option>
                </select>
                @include('partials/field_error', ['field'=>'github.openai_model'])
                <p class="form-help">
                    {{ __('GPT-5 Mini is recommended for most use cases. GPT-5 will be used automatically for complex requests.') }}
                </p>
            </div>
        </div>

        <!-- Issue Template Configuration -->
        <div class="form-group">
            <label class="col-sm-2 control-label">{{ __('AI Prompt Template') }}</label>
            <div class="col-sm-10">
                <textarea class="form-control" name="settings[github.ai_prompt_template]" rows="15" placeholder="Create a GitHub issue from this customer support conversation.

Customer: {customer_name}
Customer Email: {customer_email}
FreeScout URL: {conversation_url}
Status: {status}

Conversation:
{conversation_text}

Requirements:
1. Create a clear, professional issue title (max 80 characters)
2. Create a detailed issue body with these sections:
   - **Problem Summary**: Brief description of the issue
   - **Customer Details**: name: {customer_name}, email: {customer_email}
   - **Root Cause Analysis**: Include any diagnostic findings, reproduction confirmations, or technical analysis from support team (e.g., &quot;CSS issue&quot;, &quot;element inspection revealed&quot;, &quot;reproduced on test site&quot;)
   - **Steps to Reproduce**: Any reproduction steps mentioned by customer or support team
   - **Troubleshooting Performed**: Methods used to isolate the issue (Health Check plugin, plugin conflicts, etc.)
   - **Plugin Conflicts**: Specific conflicting plugins identified
   - **Support Team Findings**: Key diagnostic information from internal notes (inspection results, confirmed reproduction, technical analysis)
   - **Customer Environment**: Setup details and troubleshooting methods used

3. Pay special attention to support team internal notes - these often contain crucial diagnostic information
4. Use proper GitHub markdown formatting with clear sections
5. Be professional and technical in tone
6. Make the issue actionable for developers by including all diagnostic details

Respond with valid JSON in this format:
{
  &quot;title&quot;: &quot;Issue title here&quot;,
  &quot;body&quot;: &quot;Issue body with markdown formatting&quot;
}">{{ old('settings.github.ai_prompt_template', \Option::get('github.ai_prompt_template', 'Create a GitHub issue from this customer support conversation.

Customer: {customer_name}
Customer Email: {customer_email}
FreeScout URL: {conversation_url}
Status: {status}

Conversation:
{conversation_text}

Requirements:
1. Create a clear, professional issue title (max 80 characters)
2. Create a detailed issue body with these sections:
   - **Problem Summary**: Brief description of the issue
   - **Customer Details**: name: {customer_name}, email: {customer_email}
   - **Root Cause Analysis**: Include any diagnostic findings, reproduction confirmations, or technical analysis from support team (e.g., "CSS issue", "element inspection revealed", "reproduced on test site")
   - **Steps to Reproduce**: Any reproduction steps mentioned by customer or support team
   - **Troubleshooting Performed**: Methods used to isolate the issue (Health Check plugin, plugin conflicts, etc.)
   - **Plugin Conflicts**: Specific conflicting plugins identified
   - **Support Team Findings**: Key diagnostic information from internal notes (inspection results, confirmed reproduction, technical analysis)
   - **Customer Environment**: Setup details and troubleshooting methods used

3. Pay special attention to support team internal notes - these often contain crucial diagnostic information
4. Use proper GitHub markdown formatting with clear sections
5. Be professional and technical in tone
6. Make the issue actionable for developers by including all diagnostic details

Respond with valid JSON in this format:
{
  "title": "Issue title here",
  "body": "Issue body with markdown formatting"
}')) }}</textarea>
                <p class="form-help">
                    {{ __('Custom prompt template for AI issue generation. Available variables: {customer_name}, {conversation_url}, {status}, {conversation_text}') }}
                </p>
            </div>
        </div>

        <div class="form-group">
            <label class="col-sm-2 control-label">{{ __('Manual Template') }}</label>
            <div class="col-sm-10">
                <textarea class="form-control" name="settings[github.manual_template]" rows="15" placeholder="## Summary

{conversation_summary}

## Customer Information

- **Name:** {customer_name}
- **Email:** {customer_email}
- **Subject:** {subject}

## Technical Details

{technical_details}

## Original Message

```
{customer_message}
```

## Metadata

- **FreeScout URL:** {conversation_url}
- **Status:** {status}
- **Created:** {created_at}
- **Messages:** {thread_count}">{{ old('settings.github.manual_template', \Option::get('github.manual_template', '## Summary

{conversation_summary}

## Customer Information

- **Name:** {customer_name}
- **Email:** {customer_email}
- **Subject:** {subject}

## Technical Details

{technical_details}

## Original Message

```
{customer_message}
```

## Metadata

- **FreeScout URL:** {conversation_url}
- **Status:** {status}
- **Created:** {created_at}
- **Messages:** {thread_count}')) }}</textarea>
                <p class="form-help">
                    {{ __('Template for manual issue generation (when AI is not available). Available variables: {customer_name}, {customer_email}, {subject}, {conversation_url}, {status}, {created_at}, {customer_message}, {conversation_summary}, {technical_details}, {thread_count}') }}
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
            <label for="github_allowed_labels" class="col-sm-2 control-label">{{ __('Allowed Auto-assign Labels') }}</label>
            <div class="col-sm-6">
                <select class="form-control select2" name="settings[github.allowed_labels][]" id="github_allowed_labels" multiple="multiple" data-placeholder="{{ __('Select allowed labels...') }}">
                    <!-- Labels will be populated by JavaScript -->
                </select>
                @include('partials/field_error', ['field'=>'github.allowed_labels'])
                <p class="form-help">
                    {{ __('Select which labels AI can automatically assign to new issues. All labels are allowed by default. Remove labels you don\'t want AI to assign automatically.') }}
                </p>
                <div id="github-labels-loading" class="text-muted" style="display: none;">
                    <i class="glyphicon glyphicon-refresh glyphicon-spin"></i> {{ __('Loading labels...') }}
                </div>
            </div>
            <div class="col-sm-2">
                <button type="button" class="btn btn-default" id="refresh-allowed-labels">
                    <i class="glyphicon glyphicon-refresh"></i> {{ __('Refresh') }}
                </button>
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
                            <i class="glyphicon glyphicon-plus"></i> {{ __('Add Mapping') }}
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

