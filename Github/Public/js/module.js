/**
 * GitHub Module JavaScript
 * Following FreeScout Jira module pattern
 */

var GitHub = {
    config: {
        debounceDelay: 300,
        searchMinLength: 2,
        maxSearchResults: 10
    },
    cache: {
        repositories: null
    },
    warningsShown: [] // Track warnings to prevent duplicates
};

function githubInitSettings() {
    $(document).ready(function() {
        // Test connection button
        $("#test-connection").click(function(e) {
            e.preventDefault();
            var button = $(this);
            var token = $('#github_token').val();

            if (!token) {
                showFloatingAlert('error', 'Please enter a GitHub token first');
                return;
            }

            button.button('loading');

            fsAjax({
                token: token
            }, 
            laroute.route('github.test_connection'), 
            function(response) {
                button.button('reset');
                if (isAjaxSuccess(response)) {
                    githubShowConnectionResult(response);
                    if (response.repositories) {
                        githubPopulateRepositories(response.repositories);
                    }
                } else {
                    githubShowConnectionResult(response);
                }
            }, true, function(response) {
                button.button('reset');
                showFloatingAlert('error', Lang.get("messages.ajax_error"));
            });
        });

        // Refresh repositories button
        $("#refresh-repositories").click(function(e) {
            e.preventDefault();
            githubLoadRepositories();
        });

        // Repository change handler
        $("#github_default_repository").change(function() {
            var repository = $(this).val();
            if (repository) {
                githubLoadLabelMappings(repository);
                $('#label-mapping-section').show();
            } else {
                $('#label-mapping-section').hide();
            }
        });

        // Add label mapping
        $("#add-label-mapping").click(function(e) {
            e.preventDefault();
            githubAddLabelMappingRow();
        });

        // Remove label mapping
        $(document).on('click', '.remove-mapping', function(e) {
            e.preventDefault();
            $(this).closest('.label-mapping-row').remove();
        });
    });
}

function githubInitConversation() {
    $(document).ready(function() {
        // Issue actions
        $(document).on('click', '.github-issue-action', function(e) {
            e.preventDefault();
            var link = $(this);
            var action = link.data('action');
            var issueId = link.data('issue-id');
            
            if (action === 'unlink') {
                if (confirm('Are you sure you want to unlink this issue?')) {
                    githubUnlinkIssue(issueId);
                }
            } else if (action === 'refresh') {
                githubRefreshIssue(issueId);
            }
        });
    });
}

function githubInitModals() {
    $(document).ready(function() {
        // Create issue modal
        $('#github-create-issue-modal').on('show.bs.modal', function() {
            // Use cached repositories if available to avoid unnecessary API calls
            if (GitHub.cache.repositories && GitHub.cache.repositories.length > 0) {
                githubPopulateRepositories(GitHub.cache.repositories);
            } else {
                // Try localStorage cache
                var cachedRepos = githubGetCachedRepositories();
                if (cachedRepos) {
                    githubPopulateRepositories(cachedRepos);
                } else {
                    githubLoadRepositories();
                }
            }
            $('#github-create-issue-form')[0].reset();
            // Restore default repository after form reset
            setTimeout(function() {
                githubSetDefaultRepository('#github-repository');
                
                // Auto-generate content if fields are empty
                if (!$('#github-issue-title').val() && !$('#github-issue-body').val()) {
                    githubGenerateIssueContent();
                }
            }, 100);
        });

        // Link issue modal
        $('#github-link-issue-modal').on('show.bs.modal', function() {
            
            // Use cached repositories if available to avoid unnecessary API calls
            if (GitHub.cache.repositories && GitHub.cache.repositories.length > 0) {
                githubPopulateRepositories(GitHub.cache.repositories);
            } else {
                // Try localStorage cache
                var cachedRepos = githubGetCachedRepositories();
                if (cachedRepos) {
                    githubPopulateRepositories(cachedRepos);
                } else {
                    githubLoadRepositories();
                }
            }
            $('#github-link-issue-form')[0].reset();
            $('#github-search-results').hide();
            // Restore default repository after form reset
            setTimeout(function() {
                githubSetDefaultRepository('#github-link-repository');
            }, 10);
        });

        // Repository change in create modal
        $(document).on('change', '#github-repository', function() {
            var repository = $(this).val();
            if (repository) {
                githubLoadRepositoryLabels(repository);
            }
        });

        
        // Manual generate content button
        $(document).on('click', '#github-generate-content-btn', function(e) {
            e.preventDefault();
            githubGenerateIssueContent();
        });

        // Issue search
        var searchTimeout;
        $(document).on('input', '#github-issue-search', function() {
            clearTimeout(searchTimeout);
            var query = $(this).val();
            var repository = $('#github-link-repository').val();
            
            searchTimeout = setTimeout(function() {
                if (repository && query.length >= GitHub.config.searchMinLength) {
                    githubSearchIssues(repository, query);
                } else {
                    $('#github-search-results').hide();
                }
            }, GitHub.config.debounceDelay);
        });

        // Select search result
        $(document).on('click', '.github-search-result-item', function() {
            var issueNumber = $(this).data('issue-number');
            $('#github-issue-number').val(issueNumber);
            $('#github-search-results').hide();
        });

        // Create issue button
        $(document).on('click', '#github-create-issue-btn', function(e) {
            e.preventDefault();
            var button = $(this);
            button.button('loading');

            var data = new FormData();
            var form = $('#github-create-issue-form').serializeArray();
            for (var field in form) {
                data.append(form[field].name, form[field].value);
            }
            data.append('conversation_id', getGlobalAttr('conversation_id'));

            fsAjax(
                data,
                laroute.route('github.create_issue'),
                function(response) {
                    button.button('reset');
                    if (isAjaxSuccess(response)) {
                        $('#github-create-issue-modal').modal('hide');
                        window.location.href = '';
                    } else {
                        githubShowAjaxError(response);
                    }
                }, true,
                function(xhr) {
                    button.button('reset');
                    githubShowAjaxError(xhr.responseJSON || {message: Lang.get("messages.ajax_error")});
                }, {
                    cache: false,
                    contentType: false,
                    processData: false
                }
            );
        });

        // Link issue button
        $(document).on('click', '#github-link-issue-btn', function(e) {
            e.preventDefault();
            var button = $(this);
            button.button('loading');

            var data = new FormData();
            var form = $('#github-link-issue-form').serializeArray();
            for (var field in form) {
                data.append(form[field].name, form[field].value);
            }
            data.append('conversation_id', getGlobalAttr('conversation_id'));

            fsAjax(
                data,
                laroute.route('github.link_issue'),
                function(response) {
                    button.button('reset');
                    if (isAjaxSuccess(response)) {
                        $('#github-link-issue-modal').modal('hide');
                        window.location.href = '';
                    } else {
                        githubShowAjaxError(response);
                    }
                }, true,
                function(xhr) {
                    button.button('reset');
                    githubShowAjaxError(xhr.responseJSON || {message: Lang.get("messages.ajax_error")});
                }, {
                    cache: false,
                    contentType: false,
                    processData: false
                }
            );
        });
    });
}

function githubLoadRepositories() {
    var $loadingDiv = $('#github-repositories-loading');
    var $refreshBtn = $('#refresh-repositories');
    
    // Show loading indicator
    $loadingDiv.show();
    $refreshBtn.find('.glyphicon').addClass('glyphicon-spin');
    
    fsAjax({}, 
    laroute.route('github.repositories'), 
    function(response) {
        if (isAjaxSuccess(response)) {
            githubPopulateRepositories(response.repositories);
            
            // Cache repositories in localStorage with timestamp
            var cacheData = {
                repositories: response.repositories,
                timestamp: Date.now(),
                token_hash: $('#github_token').val() ? btoa($('#github_token').val()).slice(-8) : null // Last 8 chars of token for validation
            };
            localStorage.setItem('github_repositories_cache', JSON.stringify(cacheData));
        } else {
            showFloatingAlert('error', 'Failed to load repositories: ' + (response.message || 'Unknown error'));
        }
        $loadingDiv.hide();
        $refreshBtn.find('.glyphicon').removeClass('glyphicon-spin');
    }, true, function() {
        // Error callback
        $loadingDiv.hide();
        $refreshBtn.find('.glyphicon').removeClass('glyphicon-spin');
        showFloatingAlert('error', 'Failed to load repositories');
    });
}

// Check if we have cached repositories
function githubGetCachedRepositories() {
    try {
        var cached = localStorage.getItem('github_repositories_cache');
        if (!cached) return null;
        
        var cacheData = JSON.parse(cached);
        var currentTokenHash = $('#github_token').val() ? btoa($('#github_token').val()).slice(-8) : null;
        
        // Check if cache is less than 1 hour old and token matches
        var maxAge = 60 * 60 * 1000; // 1 hour
        var isValid = (Date.now() - cacheData.timestamp) < maxAge && 
                     cacheData.token_hash === currentTokenHash &&
                     cacheData.repositories && cacheData.repositories.length > 0;
        
        if (isValid) {
            return cacheData.repositories;
        } else {
            localStorage.removeItem('github_repositories_cache');
            return null;
        }
    } catch (e) {
        localStorage.removeItem('github_repositories_cache');
        return null;
    }
}

// Helper function to set default repository from DOM data
function githubSetDefaultRepository(selectId) {
    var select = $(selectId);
    if (select.length === 0) return;
    
    // Check for backend default first
    if (GitHub.defaultRepository && selectId !== '#github_default_repository') {
        select.val(GitHub.defaultRepository).trigger('change');
        return;
    }
    
    // Check if there's already a selected option in the HTML (from Blade template)
    var defaultOption = select.find('option[selected]').first();
    if (defaultOption.length > 0) {
        select.val(defaultOption.val()).trigger('change');
    }
}

function githubPopulateRepositories(repositories) {
    // Cache repositories for reuse
    GitHub.cache.repositories = repositories;
    
    var selects = ['#github_default_repository', '#github-repository', '#github-link-repository'];
    
    $.each(selects, function(i, selectId) {
        var select = $(selectId);
        if (select.length === 0) return;
        
        var currentValue = select.val();
        var defaultOption = select.find('option[selected]').first();
        var defaultValue = defaultOption.length > 0 ? defaultOption.val() : '';
        
        // Use GitHub.defaultRepository if available and we're not in settings
        var backendDefault = (selectId !== '#github_default_repository' && GitHub.defaultRepository) ? GitHub.defaultRepository : '';
        
        // For settings page, preserve any manually entered value
        if (selectId === '#github_default_repository' && currentValue) {
            // Remove all options except the placeholder and current value
            select.find('option').each(function() {
                if ($(this).val() !== '' && $(this).val() !== currentValue) {
                    $(this).remove();
                }
            });
        } else {
            select.empty().append('<option value="">' + Lang.get("messages.select_repository") + '</option>');
        }
        
        // Determine which value should be selected (priority: current -> backend default -> template default)
        var valueToSelect = currentValue || backendDefault || defaultValue;
        
        // Add repositories that have issues enabled
        var foundRepository = false;
        $.each(repositories, function(i, repo) {
            if (repo.has_issues) {
                // Check if option already exists
                if (select.find('option[value="' + repo.full_name + '"]').length === 0) {
                    var selected = repo.full_name === valueToSelect ? 'selected' : '';
                    if (repo.full_name === valueToSelect) {
                        foundRepository = true;
                    }
                    select.append('<option value="' + repo.full_name + '" ' + selected + '>' + repo.full_name + '</option>');
                }
            }
        });
        
        // Set the value if we have a value to select
        if (valueToSelect) {
            select.val(valueToSelect);
        }
        
        // Show warning if repository not found
        if (valueToSelect && repositories.length > 0 && !foundRepository) {
            // Only show warning once per repository
            var warningKey = 'repo_not_found_' + valueToSelect;
            if (GitHub.warningsShown.indexOf(warningKey) === -1) {
                GitHub.warningsShown.push(warningKey);
                showFloatingAlert('warning', Lang.get("messages.current_repository_not_found") + ': ' + valueToSelect);
            }
        }
    });
}

function githubLoadRepositoryLabels(repository) {
    // Use laroute to generate URL with encoded parameter
    var url = laroute.route('github.labels', { repository: repository });
    
    $.ajax({
        url: url,
        type: 'GET',
        success: function(response) {
            if (response.status === 'success') {
                githubPopulateLabels(response.data);
            }
        },
        error: function(xhr) {
            console.error('Failed to load labels:', xhr);
        }
    });
}

function githubPopulateLabels(labels) {
    var select = $('#github-issue-labels');
    select.empty();
    
    $.each(labels, function(i, label) {
        select.append('<option value="' + label.name + '">' + label.name + '</option>');
    });
}

function githubLoadLabelMappings(repository) {
    $.ajax({
        url: laroute.route('github.label_mappings'),
        type: 'GET',
        data: { repository: repository },
        success: function(response) {
            if (response.status === 'success') {
                githubRenderLabelMappings(response.data);
            }
        },
        error: function(xhr) {
            console.error('Failed to load label mappings:', xhr);
        }
    });
}

function githubRenderLabelMappings(mappings) {
    var container = $('#label-mappings-container');
    container.empty();

    if (mappings.length === 0) {
        container.html('<p class="text-muted">No label mappings configured</p>');
        return;
    }

    $.each(mappings, function(i, mapping) {
        githubAddLabelMappingRow(mapping);
    });
}

function githubAddLabelMappingRow(mapping) {
    mapping = mapping || {};
    
    var html = '<div class="label-mapping-row">' +
        '<input type="text" class="form-control" name="freescout_tag" placeholder="FreeScout Tag" value="' + (mapping.freescout_tag || '') + '">' +
        '<span>→</span>' +
        '<input type="text" class="form-control" name="github_label" placeholder="GitHub Label" value="' + (mapping.github_label || '') + '">' +
        '<input type="number" class="form-control" name="confidence_threshold" placeholder="0.80" value="' + (mapping.confidence_threshold || 0.80) + '" min="0" max="1" step="0.01">' +
        '<button type="button" class="btn btn-danger btn-sm remove-mapping">' +
            '<i class="glyphicon glyphicon-trash"></i>' +
        '</button>' +
    '</div>';

    $('#label-mappings-container').append(html);
}

function githubSearchIssues(repository, query) {
    fsAjax({
        repository: repository,
        query: query,
        per_page: GitHub.config.maxSearchResults
    }, 
    laroute.route('github.search_issues'), 
    function(response) {
        if (isAjaxSuccess(response)) {
            githubDisplaySearchResults(response.issues);
        }
    }, true);
}

function githubDisplaySearchResults(issues) {
    var container = $('#github-search-results-list');
    container.empty();
    
    if (issues.length === 0) {
        container.html('<p class="text-muted">No issues found</p>');
    } else {
        $.each(issues, function(i, issue) {
            var badgeClass = issue.state === 'open' ? 'success' : 'secondary';
            var html = '<div class="github-search-result-item" data-issue-number="' + issue.number + '">' +
                '<div class="github-search-result-number">#' + issue.number + '</div>' +
                '<div class="github-search-result-title">' + issue.title + '</div>' +
                '<div class="github-search-result-meta">' +
                    '<span class="badge badge-' + badgeClass + '">' + issue.state + '</span>' +
                    ' • Updated ' + githubFormatDate(issue.updated_at) +
                '</div>' +
            '</div>';
            container.append(html);
        });
    }
    
    $('#github-search-results').show();
}

function githubGenerateIssueContent() {
    var conversationId = $('#github-create-issue-form input[name="conversation_id"]').val();
    
    // Fallback to global conversation ID if not found in form
    if (!conversationId) {
        conversationId = getGlobalAttr('conversation_id');
    }
    
    if (!conversationId) {
        showFloatingAlert('error', 'No conversation ID found');
        console.error('GitHub: Could not find conversation ID in form or global attributes');
        return;
    }
    
    
    // Show loading state
    var $titleField = $('#github-issue-title');
    var $bodyField = $('#github-issue-body');
    var $generateBtn = $('#github-generate-content-btn');
    
    $generateBtn.prop('disabled', true).find('i').removeClass('glyphicon-flash').addClass('glyphicon-refresh glyphicon-spin');
    
    $.ajax({
        url: laroute.route('github.generate_content'),
        type: 'POST',
        data: {
            conversation_id: conversationId,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.status === 'success') {
                if (response.data.title && !$titleField.val()) {
                    $titleField.val(response.data.title);
                }
                if (response.data.body && !$bodyField.val()) {
                    $bodyField.val(response.data.body);
                }
                showFloatingAlert('success', 'Content generated successfully');
            } else {
                showFloatingAlert('error', response.message || 'Failed to generate content');
            }
        },
        error: function(xhr) {
            console.error('GitHub: Generate content error:', xhr);
            var response = xhr.responseJSON || {};
            var errorMessage = response.message || 'Failed to generate content';
            
            // Add more detailed error info for debugging
            if (xhr.status === 500 && !response.message) {
                errorMessage = 'Server error occurred. Check server logs for details.';
            } else if (xhr.status === 422 && response.errors) {
                var errors = [];
                for (var field in response.errors) {
                    errors = errors.concat(response.errors[field]);
                }
                errorMessage = errors.join(', ');
            }
            
            showFloatingAlert('error', errorMessage);
        },
        complete: function() {
            $generateBtn.prop('disabled', false).find('i').removeClass('glyphicon-refresh glyphicon-spin').addClass('glyphicon-flash');
        }
    });
}

function githubFormatDate(dateString) {
    return new Date(dateString).toLocaleDateString();
}

function githubShowAjaxError(response) {
    var errorMessage = 'An error occurred';
    
    if (response.message) {
        errorMessage = response.message;
        
        // If there are validation errors, append them
        if (response.errors) {
            var errorDetails = [];
            for (var field in response.errors) {
                if (response.errors.hasOwnProperty(field)) {
                    var fieldErrors = response.errors[field];
                    if (Array.isArray(fieldErrors)) {
                        errorDetails = errorDetails.concat(fieldErrors);
                    }
                }
            }
            if (errorDetails.length > 0) {
                errorMessage += ':\n• ' + errorDetails.join('\n• ');
            }
        }
    } else if (response.errors) {
        // Handle case where there's no main message but there are errors
        var errors = [];
        for (var field in response.errors) {
            if (response.errors.hasOwnProperty(field)) {
                errors = errors.concat(response.errors[field]);
            }
        }
        errorMessage = errors.length > 0 ? errors.join('\n') : 'Validation failed';
    }
    
    showFloatingAlert('error', errorMessage);
}

function githubShowConnectionResult(response) {
    var $resultDiv = $('#github-connection-result');
    var $alert = $resultDiv.find('.alert');
    var $message = $resultDiv.find('.github-connection-message');
    
    // Remove existing classes
    $alert.removeClass('alert-success alert-danger alert-warning');
    
    if (response.status === 'success') {
        $alert.addClass('alert-success');
        var message = '<strong>' + Lang.get("messages.successful") + '</strong><br>';
        
        if (response.user) {
            message += Lang.get("messages.connected_as") + ': ' + response.user + '<br>';
        }
        if (response.permissions) {
            message += Lang.get("messages.permissions") + ': ' + response.permissions.join(', ') + '<br>';
        }
        if (response.rate_limit) {
            message += Lang.get("messages.api_calls_remaining") + ': ' + response.rate_limit.remaining + '/' + response.rate_limit.limit;
        }
        
        $message.html(message);
    } else {
        $alert.addClass('alert-danger');
        var errorMessage = '<strong>' + Lang.get("messages.error") + ':</strong> ' + (response.message || 'Unknown error');
        
        // Add troubleshooting hints based on error type
        if (response.message && response.message.includes('401')) {
            errorMessage += '<br><small>' + Lang.get("messages.check_token_valid") + '</small>';
        } else if (response.message && response.message.includes('404')) {
            errorMessage += '<br><small>' + Lang.get("messages.check_token_permissions") + '</small>';
        }
        
        $message.html(errorMessage);
    }
    
    $resultDiv.fadeIn();
    
    // Also show floating alert for quick feedback
    var alertType = response.status === 'success' ? 'success' : 'error';
    showFloatingAlert(alertType, response.message || 'Unknown response');
}

// Auto-initialize when DOM is ready
$(document).ready(function() {
    // Check if we're on a page with GitHub sidebar
    var $githubSidebar = $('.github-sidebar-block');
    if ($githubSidebar.length > 0) {
        // Get default repository from data attribute
        var defaultRepo = $githubSidebar.data('default-repository');
        if (defaultRepo) {
            GitHub.defaultRepository = defaultRepo;
        }
        
        // Initialize the GitHub modals functionality
        githubInitModals();
        
        // Initialize sidebar action handlers
        githubInitSidebarActions();
        
        // Load repositories into cache if not already loaded
        if (!GitHub.cache.repositories) {
            githubLoadRepositories();
        }
    }
    
    // Check if we're on the settings page
    if ($('#github_default_repository').length > 0) {
        githubInitSettings();
        
        // Auto-load repositories if token exists
        if ($('#github_token').val()) {
            // Try to load from cache first
            var cachedRepos = githubGetCachedRepositories();
            if (cachedRepos) {
                githubPopulateRepositories(cachedRepos);
            } else {
                githubLoadRepositories();
            }
        }
    }
});

// Missing functions that were in the sidebar template
function githubCreateIssue() {
    $('#github-create-issue-btn').click(function() {
        var formData = $('#github-create-issue-form').serialize();
        var $btn = $(this);
        
        $btn.prop('disabled', true);
        $btn.find('.glyphicon').removeClass('glyphicon-plus').addClass('glyphicon-refresh glyphicon-spin');
        
        $.ajax({
            url: laroute.route('github.create_issue'),
            type: 'POST',
            data: formData + '&_token=' + $('meta[name="csrf-token"]').attr('content'),
            success: function(response) {
                if (response.status === 'success') {
                    $('#github-create-issue-modal').modal('hide');
                    showFloatingAlert('success', response.message);
                    window.location.reload(); // Refresh to show new issue
                } else {
                    showFloatingAlert('error', response.message);
                }
            },
            error: function(xhr) {
                var response = xhr.responseJSON || {};
                var errorMessage = 'An error occurred';
                
                if (response.message) {
                    errorMessage = response.message;
                } else if (response.errors) {
                    // Handle validation errors
                    var errors = [];
                    for (var field in response.errors) {
                        if (response.errors.hasOwnProperty(field)) {
                            errors = errors.concat(response.errors[field]);
                        }
                    }
                    errorMessage = errors.length > 0 ? errors.join(', ') : 'Validation failed';
                } else if (xhr.status === 422) {
                    errorMessage = 'The given data was invalid. Please check your input and try again.';
                } else if (xhr.status === 403) {
                    errorMessage = 'You do not have permission to perform this action.';
                } else if (xhr.status === 404) {
                    errorMessage = 'The requested resource was not found.';
                } else if (xhr.status >= 500) {
                    errorMessage = 'Server error occurred. Please try again later.';
                }
                
                showFloatingAlert('error', errorMessage);
            },
            complete: function() {
                $btn.prop('disabled', false);
                $btn.find('.glyphicon').removeClass('glyphicon-refresh glyphicon-spin').addClass('glyphicon-plus');
            }
        });
    });
}

function githubLinkIssue() {
    $('#github-link-issue-btn').click(function() {
        var formData = $('#github-link-issue-form').serialize();
        var $btn = $(this);
        
        $btn.prop('disabled', true);
        $btn.find('.glyphicon').removeClass('glyphicon-link').addClass('glyphicon-refresh glyphicon-spin');
        
        $.ajax({
            url: laroute.route('github.link_issue'),
            type: 'POST',
            data: formData + '&_token=' + $('meta[name="csrf-token"]').attr('content'),
            success: function(response) {
                if (response.status === 'success') {
                    $('#github-link-issue-modal').modal('hide');
                    showFloatingAlert('success', response.message);
                    window.location.reload(); // Refresh to show linked issue
                } else {
                    showFloatingAlert('error', response.message);
                }
            },
            error: function(xhr) {
                var response = xhr.responseJSON || {};
                var errorMessage = 'An error occurred';
                
                if (response.message) {
                    errorMessage = response.message;
                } else if (response.errors) {
                    // Handle validation errors
                    var errors = [];
                    for (var field in response.errors) {
                        if (response.errors.hasOwnProperty(field)) {
                            errors = errors.concat(response.errors[field]);
                        }
                    }
                    errorMessage = errors.length > 0 ? errors.join(', ') : 'Validation failed';
                } else if (xhr.status === 422) {
                    errorMessage = 'The given data was invalid. Please check your input and try again.';
                } else if (xhr.status === 403) {
                    errorMessage = 'You do not have permission to perform this action.';
                } else if (xhr.status === 404) {
                    errorMessage = 'The requested resource was not found.';
                } else if (xhr.status >= 500) {
                    errorMessage = 'Server error occurred. Please try again later.';
                }
                
                showFloatingAlert('error', errorMessage);
            },
            complete: function() {
                $btn.prop('disabled', false);
                $btn.find('.glyphicon').removeClass('glyphicon-refresh glyphicon-spin').addClass('glyphicon-link');
            }
        });
    });
}

function githubInitSidebarActions() {
    $(document).ready(function() {
        // Initialize create and link issue handlers
        githubCreateIssue();
        githubLinkIssue();
        
        // Issue actions
        $(document).on('click', '.github-issue-action', function(e) {
            e.preventDefault();
            var action = $(this).data('action');
            var issueId = $(this).data('issue-id');
            
            if (action === 'unlink') {
                if (confirm('Are you sure you want to unlink this issue?')) {
                    githubUnlinkIssue(issueId);
                }
            } else if (action === 'refresh') {
                githubRefreshIssue(issueId);
            }
        });
        
        // Search result selection
        $(document).on('click', '.github-search-result-item', function() {
            var issueNumber = $(this).data('issue-number');
            $('#github-issue-number').val(issueNumber);
            $('#github-search-results').hide();
        });
    });
}

function githubUnlinkIssue(issueId) {
    $.ajax({
        url: laroute.route('github.unlink_issue'),
        type: 'POST',
        data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            conversation_id: $('#github-create-issue-form input[name="conversation_id"]').val(),
            issue_id: issueId
        },
        success: function(response) {
            if (response.status === 'success') {
                showFloatingAlert('success', response.message);
                window.location.reload();
            } else {
                showFloatingAlert('error', response.message);
            }
        }
    });
}

function githubRefreshIssue(issueId) {
    $('[data-issue-id="' + issueId + '"]').find('.glyphicon-refresh').addClass('glyphicon-spin');
    
    var url = laroute.route('github.issue_details', {id: issueId});
    
    $.ajax({
        url: url,
        type: 'GET',
        success: function(response) {
            if (response.status === 'success') {
                window.location.reload();
            }
        },
        error: function(xhr, status, error) {
            showFloatingAlert('error', 'Failed to refresh issue: ' + error);
        },
        complete: function() {
            $('[data-issue-id="' + issueId + '"]').find('.glyphicon-refresh').removeClass('glyphicon-spin');
        }
    });
}