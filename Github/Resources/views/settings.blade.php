@extends('layouts.app')

@section('title', __('GitHub Settings'))

@section('content')
<div class="section-heading">
    {{ __('GitHub Integration') }}
</div>

<div class="col-xs-12">
    <form class="form-horizontal margin-top" method="POST" action="{{ route('settings.save') }}">
        {{ csrf_field() }}

        <!-- GitHub API Configuration -->
        <div class="form-group{{ $errors->has('github.token') ? ' has-error' : '' }}">
            <label for="github_token" class="col-sm-2 control-label">{{ __('GitHub Token') }}</label>
            <div class="col-sm-6">
                <input type="password" class="form-control" name="settings[github.token]" id="github_token" value="{{ old('settings.github.token', $settings['github.token'] ?? '') }}" placeholder="{{ __('Personal Access Token or GitHub App Token') }}">
                @include('partials/field_error', ['field'=>'github.token'])
                <p class="form-help">
                    {{ __('Create a') }} 
                    <a href="https://github.com/settings/tokens" target="_blank">{{ __('Personal Access Token') }}</a>
                    {{ __('with repo and webhook permissions.') }}
                </p>
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
                </select>
                @include('partials/field_error', ['field'=>'github.default_repository'])
                <p class="form-help">
                    {{ __('Default repository for creating issues (format: owner/repo)') }}
                </p>
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
                <input type="password" class="form-control" name="settings[github.webhook_secret]" id="github_webhook_secret" value="{{ old('settings.github.webhook_secret', $settings['github.webhook_secret'] ?? '') }}" placeholder="{{ __('Optional webhook secret for security') }}">
                @include('partials/field_error', ['field'=>'github.webhook_secret'])
                <p class="form-help">
                    {{ __('Webhook URL:') }} <code>{{ route('github.webhook') }}</code>
                </p>
            </div>
        </div>

        <!-- AI Configuration -->
        <div class="form-group{{ $errors->has('github.ai_service') ? ' has-error' : '' }}">
            <label for="github_ai_service" class="col-sm-2 control-label">{{ __('AI Service') }}</label>
            <div class="col-sm-6">
                <select class="form-control" name="settings[github.ai_service]" id="github_ai_service">
                    <option value="">{{ __('Disabled') }}</option>
                    <option value="openai" {{ old('settings.github.ai_service', $settings['github.ai_service'] ?? '') == 'openai' ? 'selected' : '' }}>OpenAI</option>
                    <option value="claude" {{ old('settings.github.ai_service', $settings['github.ai_service'] ?? '') == 'claude' ? 'selected' : '' }}>Claude</option>
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
                <input type="password" class="form-control" name="settings[github.ai_api_key]" id="github_ai_api_key" value="{{ old('settings.github.ai_api_key', $settings['github.ai_api_key'] ?? '') }}" placeholder="{{ __('API key for selected AI service') }}">
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
                        <input type="checkbox" name="settings[github.create_remote_link]" value="1" {{ old('settings.github.create_remote_link', $settings['github.create_remote_link'] ?? true) ? 'checked' : '' }}> {{ __('Add FreeScout links to GitHub issues') }}
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
                        <input type="checkbox" name="settings[github.sync_status]" value="1" {{ old('settings.github.sync_status', $settings['github.sync_status'] ?? true) ? 'checked' : '' }}> {{ __('Sync GitHub issue status with conversations') }}
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
                        <input type="checkbox" name="settings[github.auto_assign_labels]" value="1" {{ old('settings.github.auto_assign_labels', $settings['github.auto_assign_labels'] ?? true) ? 'checked' : '' }}> {{ __('Automatically assign labels to new issues') }}
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

<!-- Connection Status Modal -->
<div class="modal fade" id="connection-status-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">{{ __('Connection Test') }}</h4>
            </div>
            <div class="modal-body">
                <div id="connection-result"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('javascript')
<script>
$(document).ready(function() {
    // Test GitHub connection
    $('#test-connection').click(function() {
        var token = $('#github_token').val();
        if (!token) {
            alert('{{ __("Please enter a GitHub token first") }}');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);
        $btn.find('.fa').removeClass('fa-check-circle').addClass('fa-spinner fa-spin');

        $.ajax({
            url: '{{ route("github.test_connection") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                token: token
            },
            success: function(response) {
                showConnectionResult(response);
                if (response.status === 'success') {
                    loadRepositories();
                }
            },
            error: function(xhr) {
                var response = xhr.responseJSON || {status: 'error', message: 'Connection failed'};
                showConnectionResult(response);
            },
            complete: function() {
                $btn.prop('disabled', false);
                $btn.find('.fa').removeClass('fa-spinner fa-spin').addClass('fa-check-circle');
            }
        });
    });

    // Load repositories
    function loadRepositories() {
        $.ajax({
            url: '{{ route("github.repositories") }}',
            type: 'GET',
            success: function(response) {
                if (response.status === 'success') {
                    var $select = $('#github_default_repository');
                    var currentValue = $select.val();
                    
                    $select.empty().append('<option value="">{{ __("Select Repository") }}</option>');
                    
                    $.each(response.data, function(i, repo) {
                        var selected = repo.full_name === currentValue ? 'selected' : '';
                        $select.append('<option value="' + repo.full_name + '" ' + selected + '>' + repo.full_name + '</option>');
                    });
                }
            }
        });
    }

    // Refresh repositories
    $('#refresh-repositories').click(function() {
        loadRepositories();
    });

    // Show connection result
    function showConnectionResult(response) {
        var alertClass = response.status === 'success' ? 'alert-success' : 'alert-danger';
        var icon = response.status === 'success' ? 'fa-check-circle' : 'fa-times-circle';
        
        $('#connection-result').html(
            '<div class="alert ' + alertClass + '">' +
                '<i class="fa ' + icon + '"></i> ' + response.message +
            '</div>'
        );
        
        $('#connection-status-modal').modal('show');
    }

    // Repository selection change
    $('#github_default_repository').change(function() {
        var repository = $(this).val();
        if (repository) {
            loadLabelMappings(repository);
            $('#label-mapping-section').show();
        } else {
            $('#label-mapping-section').hide();
        }
    });

    // Load label mappings for repository
    function loadLabelMappings(repository) {
        $.ajax({
            url: '{{ route("github.label_mappings") }}',
            type: 'GET',
            data: { repository: repository },
            success: function(response) {
                if (response.status === 'success') {
                    renderLabelMappings(response.data);
                }
            }
        });
    }

    // Render label mappings
    function renderLabelMappings(mappings) {
        var $container = $('#label-mappings-container');
        $container.empty();

        if (mappings.length === 0) {
            $container.html('<p class="text-muted">{{ __("No label mappings configured") }}</p>');
            return;
        }

        $.each(mappings, function(i, mapping) {
            addLabelMappingRow(mapping);
        });
    }

    // Add label mapping row
    function addLabelMappingRow(mapping) {
        mapping = mapping || {};
        
        var html = '<div class="label-mapping-row form-inline" style="margin-bottom: 10px;">' +
            '<input type="text" class="form-control" name="freescout_tag" placeholder="{{ __("FreeScout Tag") }}" value="' + (mapping.freescout_tag || '') + '" style="width: 200px; margin-right: 10px;">' +
            '<span style="margin-right: 10px;">→</span>' +
            '<input type="text" class="form-control" name="github_label" placeholder="{{ __("GitHub Label") }}" value="' + (mapping.github_label || '') + '" style="width: 200px; margin-right: 10px;">' +
            '<input type="number" class="form-control" name="confidence_threshold" placeholder="0.80" value="' + (mapping.confidence_threshold || 0.80) + '" min="0" max="1" step="0.01" style="width: 80px; margin-right: 10px;">' +
            '<button type="button" class="btn btn-danger btn-sm remove-mapping">' +
                '<i class="fa fa-trash"></i>' +
            '</button>' +
        '</div>';

        $('#label-mappings-container').append(html);
    }

    // Add mapping button
    $('#add-label-mapping').click(function() {
        addLabelMappingRow();
    });

    // Remove mapping
    $(document).on('click', '.remove-mapping', function() {
        $(this).closest('.label-mapping-row').remove();
    });

    // Initialize - load repositories if token exists
    if ($('#github_token').val()) {
        loadRepositories();
    }

    // Initialize - show label mappings if repository is selected
    if ($('#github_default_repository').val()) {
        $('#label-mapping-section').show();
        loadLabelMappings($('#github_default_repository').val());
    }
});
</script>
@endsection