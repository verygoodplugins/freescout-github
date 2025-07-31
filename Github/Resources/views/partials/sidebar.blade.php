@if(\Option::get('github.token'))
<div class="sidebar-block github-sidebar-block" data-default-repository="{{ \Option::get('github.default_repository') }}">
    <div class="sidebar-block-header">
        <h3>
            <i class="glyphicon glyphicon-folder-open"></i>
            {{ __('GitHub Issues') }}
        </h3>
        <div class="sidebar-block-header-actions">
            @if($issues->count() > 0)
                <a href="#" class="btn btn-default btn-xs" onclick="githubManualRefreshConversation(); return false;" title="{{ __('Refresh All Issues') }}">
                    <i class="glyphicon glyphicon-refresh"></i>
                </a>
            @endif
            <a href="#" class="btn btn-default btn-xs" data-toggle="modal" data-target="#github-create-issue-modal" title="{{ __('Create Issue') }}">
                <i class="glyphicon glyphicon-plus"></i>
            </a>
            <a href="#" class="btn btn-default btn-xs" data-toggle="modal" data-target="#github-link-issue-modal" title="{{ __('Link Issue') }}">
                <i class="glyphicon glyphicon-link"></i>
            </a>
        </div>
    </div>
    
    <div class="sidebar-block-content">
        <div id="github-issues-container">
            @if($issues->count() > 0)
                @foreach($issues as $issue)
                    <div class="github-issue-item" data-issue-id="{{ $issue->id }}">
                        <div class="github-issue-header">
                            <div class="github-issue-title">
                                <a href="{{ $issue->html_url }}" target="_blank" title="{{ $issue->title }}">
                                    #{{ $issue->number }}
                                </a>
                                <span class="badge badge-{{ $issue->getStatusBadgeClass() }}">
                                    {{ ucfirst($issue->state) }}
                                </span>
                            </div>
                            <div class="github-issue-actions">
                                <a href="#" class="github-issue-action" data-action="refresh" data-issue-id="{{ $issue->id }}" title="{{ __('Refresh') }}">
                                    <i class="glyphicon glyphicon-refresh"></i>
                                </a>
                                <a href="#" class="github-issue-action" data-action="unlink" data-issue-id="{{ $issue->id }}" title="{{ __('Unlink') }}">
                                    <i class="glyphicon glyphicon-remove"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="github-issue-details">
                            <div class="github-issue-repository">
                                <i class="glyphicon glyphicon-folder-close"></i>
                                {{ $issue->getShortRepository() }}
                            </div>
                            
                            <div class="github-issue-title-full">
                                {{ \Illuminate\Support\Str::limit($issue->title, 50) }}
                            </div>
                            
                            @if($issue->labels && count($issue->labels) > 0)
                                <div class="github-issue-labels">
                                    @foreach($issue->getFormattedLabels() as $label)
                                        <span class="github-label" style="background-color: {{ $label['color'] }}">
                                            {{ $label['name'] }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                            
                            @if($issue->assignees && count($issue->assignees) > 0)
                                <div class="github-issue-assignees">
                                    <i class="glyphicon glyphicon-user"></i>
                                    {{ implode(', ', $issue->assignees) }}
                                </div>
                            @endif
                            
                            <div class="github-issue-meta">
                                <small class="text-muted">
                                    {{ __('Updated') }} {{ $issue->github_updated_at->diffForHumans() }}
                                </small>
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="github-no-issues">
                    <div class="text-muted text-center">
                        <i class="glyphicon glyphicon-folder-open" style="font-size: 2em; margin-bottom: 10px;"></i>
                        <p>{{ __('No GitHub issues linked to this conversation.') }}</p>
                        <p>
                            <a href="#" data-toggle="modal" data-target="#github-create-issue-modal" class="btn btn-sm btn-primary">
                                {{ __('Create Issue') }}
                            </a>
                            <a href="#" data-toggle="modal" data-target="#github-link-issue-modal" class="btn btn-sm btn-default">
                                {{ __('Link Existing') }}
                            </a>
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Create Issue Modal -->
<div class="modal fade" id="github-create-issue-modal" tabindex="-1" role="dialog" aria-labelledby="github-create-issue-modal-title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="github-create-issue-modal-title">
                    <i class="glyphicon glyphicon-folder-open"></i>
                    {{ __('Create GitHub Issue') }}
                </h4>
            </div>
            <div class="modal-body">
                <form id="github-create-issue-form">
                    <input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
                    
                    <div class="form-group">
                        <label for="github-repository">{{ __('Repository') }}</label>
                        <select class="form-control" name="repository" id="github-repository" required>
                            <option value="">Select Repository</option>
                            @php
                                $defaultRepo = \Option::get('github.default_repository');
                            @endphp
                            @if($defaultRepo)
                                <option value="{{ $defaultRepo }}" selected>{{ $defaultRepo }}</option>
                            @endif
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="github-issue-title">{{ __('Title') }}</label>
                        <div class="input-group">
                            <input type="text" class="form-control" name="title" id="github-issue-title" maxlength="255" required>
                            <div class="input-group-btn">
                                <button type="button" class="btn btn-default" id="github-generate-content-btn" title="{{ __('Generate with AI') }}">
                                    <i class="glyphicon glyphicon-flash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="github-issue-body">{{ __('Description') }}</label>
                        <textarea class="form-control" name="body" id="github-issue-body" rows="6"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="github-issue-labels">{{ __('Labels') }}</label>
                                <select class="form-control" name="labels[]" id="github-issue-labels" multiple>
                                    <!-- Labels will be populated via JavaScript -->
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="github-issue-assignees">{{ __('Assignees') }}</label>
                                <input type="text" class="form-control" name="assignees" id="github-issue-assignees" placeholder="{{ __('GitHub usernames (comma-separated)') }}">
                            </div>
                        </div>
                    </div>
                    
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" id="github-create-issue-btn">
                    <i class="glyphicon glyphicon-plus"></i>
                    {{ __('Create Issue') }}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Link Issue Modal -->
<div class="modal fade" id="github-link-issue-modal" tabindex="-1" role="dialog" aria-labelledby="github-link-issue-modal-title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="github-link-issue-modal-title">
                    <i class="glyphicon glyphicon-link"></i>
                    {{ __('Link GitHub Issue') }}
                </h4>
            </div>
            <div class="modal-body">
                <form id="github-link-issue-form">
                    <input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
                    
                    <div class="form-group">
                        <label for="github-link-repository">{{ __('Repository') }}</label>
                        <select class="form-control" name="repository" id="github-link-repository" required>
                            <option value="">Select Repository</option>
                            @php
                                $defaultRepo = \Option::get('github.default_repository');
                            @endphp
                            @if($defaultRepo)
                                <option value="{{ $defaultRepo }}" selected>{{ $defaultRepo }}</option>
                            @endif
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="github-issue-search">{{ __('Search Issues') }}</label>
                        <input type="text" class="form-control" id="github-issue-search" placeholder="{{ __('Search by title, number, or keywords...') }}">
                    </div>
                    
                    <div class="form-group">
                        <label for="github-issue-number">{{ __('Issue Number') }}</label>
                        <input type="number" class="form-control" name="issue_number" id="github-issue-number" min="1" required>
                    </div>
                    
                    <div id="github-search-results" class="github-search-results" style="display: none;">
                        <h5>{{ __('Search Results') }}</h5>
                        <div id="github-search-results-list"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" id="github-link-issue-btn">
                    <i class="glyphicon glyphicon-link"></i>
                    {{ __('Link Issue') }}
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.github-issue-item {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    margin-bottom: 10px;
    background: #f9f9f9;
}

.github-issue-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.github-issue-title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.github-issue-title a {
    font-weight: bold;
    color: #333;
    text-decoration: none;
}

.github-issue-title a:hover {
    text-decoration: underline;
}

.github-issue-actions {
    display: flex;
    gap: 5px;
}

.github-issue-action {
    color: #666;
    padding: 2px 5px;
    border-radius: 3px;
    text-decoration: none;
}

.github-issue-action:hover {
    background: #e6e6e6;
    color: #333;
}

.github-issue-details {
    font-size: 0.9em;
}

.github-issue-repository {
    color: #666;
    margin-bottom: 5px;
}

.github-issue-title-full {
    font-weight: 500;
    margin-bottom: 8px;
}

.github-issue-labels {
    margin-bottom: 8px;
}

.github-label {
    display: inline-block;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.8em;
    margin-right: 4px;
    margin-bottom: 2px;
}

.github-issue-assignees {
    color: #666;
    margin-bottom: 5px;
}

.github-issue-meta {
    border-top: 1px solid #eee;
    padding-top: 5px;
}

.github-no-issues {
    text-align: center;
    padding: 20px;
}

.github-search-results {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
}

.github-search-result-item {
    padding: 8px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
}

.github-search-result-item:hover {
    background: #f5f5f5;
}

.github-search-result-item:last-child {
    border-bottom: none;
}

.github-search-result-number {
    font-weight: bold;
    color: #0366d6;
}

.github-search-result-title {
    margin: 5px 0;
}

.github-search-result-meta {
    font-size: 0.9em;
    color: #666;
}

.badge-success {
    background-color: #28a745;
}

.badge-secondary {
    background-color: #6c757d;
}

.glyphicon-spin {
    -webkit-animation: spin 1s infinite linear;
    -moz-animation: spin 1s infinite linear;
    -o-animation: spin 1s infinite linear;
    animation: spin 1s infinite linear;
}

@-moz-keyframes spin {
    from { -moz-transform: rotate(0deg); }
    to { -moz-transform: rotate(360deg); }
}

@-webkit-keyframes spin {
    from { -webkit-transform: rotate(0deg); }
    to { -webkit-transform: rotate(360deg); }
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>

@endif