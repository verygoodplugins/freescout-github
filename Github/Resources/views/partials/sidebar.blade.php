@if(\Option::get('github.token'))
<div class="sidebar-block">
    <div class="sidebar-block-header">
        <h3>
            <i class="fa fa-github"></i>
            {{ __('GitHub Issues') }}
        </h3>
        <div class="sidebar-block-header-actions">
            <a href="#" class="btn btn-default btn-xs" data-toggle="modal" data-target="#github-create-issue-modal" title="{{ __('Create Issue') }}">
                <i class="fa fa-plus"></i>
            </a>
            <a href="#" class="btn btn-default btn-xs" data-toggle="modal" data-target="#github-link-issue-modal" title="{{ __('Link Issue') }}">
                <i class="fa fa-link"></i>
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
                                    <i class="fa fa-refresh"></i>
                                </a>
                                <a href="#" class="github-issue-action" data-action="unlink" data-issue-id="{{ $issue->id }}" title="{{ __('Unlink') }}">
                                    <i class="fa fa-unlink"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="github-issue-details">
                            <div class="github-issue-repository">
                                <i class="fa fa-folder"></i>
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
                                    <i class="fa fa-user"></i>
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
                        <i class="fa fa-github" style="font-size: 2em; margin-bottom: 10px;"></i>
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
                    <i class="fa fa-github"></i>
                    {{ __('Create GitHub Issue') }}
                </h4>
            </div>
            <div class="modal-body">
                <form id="github-create-issue-form">
                    <input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
                    
                    <div class="form-group">
                        <label for="github-repository">{{ __('Repository') }}</label>
                        <select class="form-control" name="repository" id="github-repository" required>
                            <option value="">{{ __('Select Repository') }}</option>
                            @if(\Option::get('github.default_repository'))
                                <option value="{{ \Option::get('github.default_repository') }}" selected>
                                    {{ \Option::get('github.default_repository') }}
                                </option>
                            @endif
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="github-issue-title">{{ __('Title') }}</label>
                        <input type="text" class="form-control" name="title" id="github-issue-title" maxlength="255" required>
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
                    
                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="auto_generate_content" id="github-auto-generate" checked>
                                {{ __('Auto-generate content from conversation') }}
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="auto_assign_labels" id="github-auto-labels" checked>
                                {{ __('Auto-assign labels based on conversation') }}
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" id="github-create-issue-btn">
                    <i class="fa fa-plus"></i>
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
                    <i class="fa fa-link"></i>
                    {{ __('Link GitHub Issue') }}
                </h4>
            </div>
            <div class="modal-body">
                <form id="github-link-issue-form">
                    <input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
                    
                    <div class="form-group">
                        <label for="github-link-repository">{{ __('Repository') }}</label>
                        <select class="form-control" name="repository" id="github-link-repository" required>
                            <option value="">{{ __('Select Repository') }}</option>
                            @if(\Option::get('github.default_repository'))
                                <option value="{{ \Option::get('github.default_repository') }}" selected>
                                    {{ \Option::get('github.default_repository') }}
                                </option>
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
                    <i class="fa fa-link"></i>
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
</style>

<script>
$(document).ready(function() {
    // Load repositories when modals are opened
    $('#github-create-issue-modal, #github-link-issue-modal').on('show.bs.modal', function() {
        loadRepositories();
    });

    // Auto-generate content when checkbox is checked
    $('#github-auto-generate').change(function() {
        if ($(this).is(':checked')) {
            generateIssueContent();
        }
    });

    // Repository change in create modal
    $('#github-repository').change(function() {
        var repository = $(this).val();
        if (repository) {
            loadRepositoryLabels(repository);
        }
    });

    // Issue search
    $('#github-issue-search').on('input', debounce(function() {
        var repository = $('#github-link-repository').val();
        var query = $(this).val();
        
        if (repository && query.length > 2) {
            searchGitHubIssues(repository, query);
        } else {
            $('#github-search-results').hide();
        }
    }, 300));

    // Create issue
    $('#github-create-issue-btn').click(function() {
        var formData = $('#github-create-issue-form').serialize();
        var $btn = $(this);
        
        $btn.prop('disabled', true);
        $btn.find('.fa').removeClass('fa-plus').addClass('fa-spinner fa-spin');
        
        $.ajax({
            url: '{{ route("github.create_issue") }}',
            type: 'POST',
            data: formData + '&_token={{ csrf_token() }}',
            success: function(response) {
                if (response.status === 'success') {
                    $('#github-create-issue-modal').modal('hide');
                    showSuccessMessage(response.message);
                    refreshGitHubIssues();
                } else {
                    showErrorMessage(response.message);
                }
            },
            error: function(xhr) {
                var response = xhr.responseJSON || {message: 'An error occurred'};
                showErrorMessage(response.message);
            },
            complete: function() {
                $btn.prop('disabled', false);
                $btn.find('.fa').removeClass('fa-spinner fa-spin').addClass('fa-plus');
            }
        });
    });

    // Link issue
    $('#github-link-issue-btn').click(function() {
        var formData = $('#github-link-issue-form').serialize();
        var $btn = $(this);
        
        $btn.prop('disabled', true);
        $btn.find('.fa').removeClass('fa-link').addClass('fa-spinner fa-spin');
        
        $.ajax({
            url: '{{ route("github.link_issue") }}',
            type: 'POST',
            data: formData + '&_token={{ csrf_token() }}',
            success: function(response) {
                if (response.status === 'success') {
                    $('#github-link-issue-modal').modal('hide');
                    showSuccessMessage(response.message);
                    refreshGitHubIssues();
                } else {
                    showErrorMessage(response.message);
                }
            },
            error: function(xhr) {
                var response = xhr.responseJSON || {message: 'An error occurred'};
                showErrorMessage(response.message);
            },
            complete: function() {
                $btn.prop('disabled', false);
                $btn.find('.fa').removeClass('fa-spinner fa-spin').addClass('fa-link');
            }
        });
    });

    // Issue actions
    $(document).on('click', '.github-issue-action', function(e) {
        e.preventDefault();
        var action = $(this).data('action');
        var issueId = $(this).data('issue-id');
        
        if (action === 'unlink') {
            if (confirm('{{ __("Are you sure you want to unlink this issue?") }}')) {
                unlinkIssue(issueId);
            }
        } else if (action === 'refresh') {
            refreshIssue(issueId);
        }
    });

    // Search result selection
    $(document).on('click', '.github-search-result-item', function() {
        var issueNumber = $(this).data('issue-number');
        $('#github-issue-number').val(issueNumber);
        $('#github-search-results').hide();
    });

    // Helper functions
    function loadRepositories() {
        $.ajax({
            url: '{{ route("github.repositories") }}',
            type: 'GET',
            success: function(response) {
                if (response.status === 'success') {
                    populateRepositorySelects(response.data);
                }
            }
        });
    }

    function populateRepositorySelects(repositories) {
        var selects = ['#github-repository', '#github-link-repository'];
        
        $.each(selects, function(i, selectId) {
            var $select = $(selectId);
            var currentValue = $select.val();
            
            $select.empty().append('<option value="">{{ __("Select Repository") }}</option>');
            
            $.each(repositories, function(i, repo) {
                if (repo.has_issues) {
                    var selected = repo.full_name === currentValue ? 'selected' : '';
                    $select.append('<option value="' + repo.full_name + '" ' + selected + '>' + repo.full_name + '</option>');
                }
            });
        });
    }

    function loadRepositoryLabels(repository) {
        $.ajax({
            url: '{{ route("github.labels", ":repository") }}'.replace(':repository', encodeURIComponent(repository)),
            type: 'GET',
            success: function(response) {
                if (response.status === 'success') {
                    populateLabelsSelect(response.data);
                }
            }
        });
    }

    function populateLabelsSelect(labels) {
        var $select = $('#github-issue-labels');
        $select.empty();
        
        $.each(labels, function(i, label) {
            $select.append('<option value="' + label.name + '">' + label.name + '</option>');
        });
    }

    function searchGitHubIssues(repository, query) {
        $.ajax({
            url: '{{ route("github.search_issues") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                repository: repository,
                query: query,
                per_page: 10
            },
            success: function(response) {
                if (response.status === 'success') {
                    displaySearchResults(response.data);
                }
            }
        });
    }

    function displaySearchResults(issues) {
        var $container = $('#github-search-results-list');
        $container.empty();
        
        if (issues.length === 0) {
            $container.html('<p class="text-muted">{{ __("No issues found") }}</p>');
        } else {
            $.each(issues, function(i, issue) {
                var html = '<div class="github-search-result-item" data-issue-number="' + issue.number + '">' +
                    '<div class="github-search-result-number">#' + issue.number + '</div>' +
                    '<div class="github-search-result-title">' + issue.title + '</div>' +
                    '<div class="github-search-result-meta">' +
                        '<span class="badge badge-' + (issue.state === 'open' ? 'success' : 'secondary') + '">' + issue.state + '</span>' +
                        ' • Updated ' + new Date(issue.updated_at).toLocaleDateString() +
                    '</div>' +
                '</div>';
                $container.append(html);
            });
        }
        
        $('#github-search-results').show();
    }

    function generateIssueContent() {
        // This would typically make an AJAX call to generate content
        // For now, just populate with basic conversation info
        if (!$('#github-issue-title').val()) {
            $('#github-issue-title').val('{{ $conversation->subject }}');
        }
    }

    function unlinkIssue(issueId) {
        $.ajax({
            url: '{{ route("github.unlink_issue") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                conversation_id: '{{ $conversation->id }}',
                issue_id: issueId
            },
            success: function(response) {
                if (response.status === 'success') {
                    showSuccessMessage(response.message);
                    refreshGitHubIssues();
                } else {
                    showErrorMessage(response.message);
                }
            }
        });
    }

    function refreshIssue(issueId) {
        // Refresh single issue from GitHub
        $('[data-issue-id="' + issueId + '"]').find('.fa-refresh').addClass('fa-spin');
        
        $.ajax({
            url: '{{ route("github.issue_details", ":id") }}'.replace(':id', issueId),
            type: 'GET',
            success: function(response) {
                if (response.status === 'success') {
                    // Update the issue display
                    refreshGitHubIssues();
                }
            },
            complete: function() {
                $('[data-issue-id="' + issueId + '"]').find('.fa-refresh').removeClass('fa-spin');
            }
        });
    }

    function refreshGitHubIssues() {
        // Reload the entire sidebar
        window.location.reload();
    }

    function showSuccessMessage(message) {
        // Show success message (implement based on FreeScout's notification system)
        console.log('Success:', message);
    }

    function showErrorMessage(message) {
        // Show error message (implement based on FreeScout's notification system)
        console.log('Error:', message);
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
});
</script>
@endif