<form class="form-horizontal margin-top margin-bottom" method="POST" action="">
    {{ csrf_field() }}

    @if ($auth_error)
        <div class="alert alert-danger">
            <strong>{{ __('Jira API authentication error') }}</strong><br/>{{ $auth_error }}
        </div>
    @endif

    <div class="form-group margin-bottom">
        <label class="col-sm-2 control-label">{{ __('Integration Status') }}</label>
        <div class="col-sm-6">
            <label class="control-label">
                @if (\Option::get('jira.active'))
                    <strong class="text-success"><i class="glyphicon glyphicon-ok"></i> {{ __('Active') }}</strong>
                @else
                    <strong class="text-warning">{{ __('Inactive') }}</strong>
                @endif
            </label>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('Jira Base URL') }}</label>
        <div class="col-sm-6">
            <input type="url" class="form-control input-sized-lg" name="settings[jira.base_url]" value="{{ $settings['jira.base_url'] }}" placeholder="{{ __('Example') }}: https://yourcompany.atlassian.net">
            <p class="form-help">
                {{ __('If you change the Jira Base URL later, previously created links in FreeScout leading to Jira issues will stop working.') }}
            </p>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('Jira Username / Email') }}</label>
        <div class="col-sm-6">
            <input type="text" class="form-control input-sized-lg" name="settings[jira.username]" value="{{ $settings['jira.username'] }}">
            <p class="form-help">
                {{ __('This user must be a Jira administrator.') }}
                {{-- __('User must have permissions to edit issues and create webhooks.') --}}
            </p>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('Jira API Token') }}</label>
        <div class="col-sm-6">
            <input type="password" class="form-control input-sized-lg" name="settings[jira.api_token]" value="{{ $settings['jira.api_token'] }}" autocomplete="new-password">
            <div class="form-help">
                <a href="https://support.atlassian.com/atlassian-account/docs/manage-api-tokens-for-your-atlassian-account/" target="_blank">{{ __('How to get an API Token in Jira Cloud?') }}</a> {{ __('For self-hosted Jira installation enter user password.') }}
            </div>
        </div>
    </div>

    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('Jira Issue Resolved Status') }}</label>
        <div class="col-sm-6">
            <input type="text" class="form-control input-sized-lg" name="settings[jira.resolved_status]" value="{{ $settings['jira.resolved_status'] }}">
            <div class="form-help">
                {{ __('Comma separated list of status names determining that the issue is resolved in Jira.') }} {{ __('Make sure to enter status names in all languages used by your Jira team.') }}
            </div>
        </div>
    </div>
    
    <div class="form-group">
        <label class="col-sm-2 control-label">{{ __('When Jira Issue Resolved') }}</label>
        <div class="col-sm-6">
            <div class="control-group">
                <label class="radio" for="jira_on_resolve_1">
                    <input type="radio" name="settings[jira.on_resolve]" value="{{ \Jira::ON_RESOLVE_ACTIVATE }}" id="jira_on_resolve_1" @if (!$settings['jira.on_resolve'] || $settings['jira.on_resolve'] == \Jira::ON_RESOLVE_ACTIVATE) checked="checked" @endif> {{ __('Change FreeScout conversation status to Active and add a note when linked Jira issue is resolved.') }}
                </label>
                <label class="radio" for="jira_on_resolve_2">
                    <input type="radio" name="settings[jira.on_resolve]" value="{{ \Jira::ON_RESOLVE_NOTE }}" id="jira_on_resolve_2" @if ($settings['jira.on_resolve'] == \Jira::ON_RESOLVE_NOTE) checked="checked" @endif> {{ __('Add a note to FreeScout conversation informing that linked Jira issue was resolved.') }}
                </label>
            </div>
        </div>
    </div>

    <div class="form-group margin-top margin-bottom">
        <div class="col-sm-6 col-sm-offset-2">
            <button type="submit" class="btn btn-primary">
                {{ __('Save') }}
            </button>
        </div>
    </div>
</form>
