/**
 * GitHub Module JavaScript
 */

(function($) {
    'use strict';

    // GitHub Module object
    window.GitHub = {
        // Configuration
        config: {
            debounceDelay: 300,
            searchMinLength: 2,
            maxSearchResults: 10
        },

        // Initialize the module
        init: function() {
            this.bindEvents();
            this.initializeComponents();
        },

        // Bind event handlers
        bindEvents: function() {
            // Settings page events
            $(document).on('click', '#test-connection', this.testConnection);
            $(document).on('click', '#refresh-repositories', this.refreshRepositories);
            $(document).on('change', '#github_default_repository', this.onRepositoryChange);
            $(document).on('click', '#add-label-mapping', this.addLabelMapping);
            $(document).on('click', '.remove-mapping', this.removeLabelMapping);

            // Sidebar events
            $(document).on('click', '.github-issue-action', this.handleIssueAction);
            $(document).on('click', '.github-search-result-item', this.selectSearchResult);

            // Modal events
            $(document).on('show.bs.modal', '#github-create-issue-modal', this.onCreateModalShow);
            $(document).on('show.bs.modal', '#github-link-issue-modal', this.onLinkModalShow);
            $(document).on('change', '#github-repository', this.onCreateRepositoryChange);
            $(document).on('change', '#github-auto-generate', this.onAutoGenerateChange);
            $(document).on('input', '#github-issue-search', this.debounce(this.searchIssues, this.config.debounceDelay));
            $(document).on('click', '#github-create-issue-btn', this.createIssue);
            $(document).on('click', '#github-link-issue-btn', this.linkIssue);
        },

        // Initialize components
        initializeComponents: function() {
            // Initialize any components that need setup
            this.initializeTags();
            this.initializeTooltips();
        },

        // Initialize tags/labels
        initializeTags: function() {
            // Initialize select2 for labels if available
            if (typeof $.fn.select2 !== 'undefined') {
                $('#github-issue-labels').select2({
                    placeholder: 'Select labels',
                    allowClear: true,
                    width: '100%'
                });
            }
        },

        // Initialize tooltips
        initializeTooltips: function() {
            $('[data-toggle="tooltip"]').tooltip();
        },

        // Test GitHub connection
        testConnection: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var token = $('#github_token').val();

            if (!token) {
                GitHub.showAlert('error', 'Please enter a GitHub token first');
                return;
            }

            GitHub.setButtonLoading($btn, true);

            $.ajax({
                url: $btn.data('url') || '/github/test-connection',
                type: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    token: token
                },
                success: function(response) {
                    GitHub.showConnectionResult(response);
                    if (response.status === 'success') {
                        GitHub.loadRepositories();
                    }
                },
                error: function(xhr) {
                    var response = xhr.responseJSON || {status: 'error', message: 'Connection failed'};
                    GitHub.showConnectionResult(response);
                },
                complete: function() {
                    GitHub.setButtonLoading($btn, false);
                }
            });
        },

        // Refresh repositories
        refreshRepositories: function(e) {
            e.preventDefault();
            GitHub.loadRepositories();
        },

        // Load repositories
        loadRepositories: function() {
            $.ajax({
                url: '/github/repositories',
                type: 'GET',
                success: function(response) {
                    if (response.status === 'success') {
                        GitHub.populateRepositorySelects(response.data);
                    }
                },
                error: function(xhr) {
                    console.error('Failed to load repositories:', xhr);
                }
            });
        },

        // Populate repository selects
        populateRepositorySelects: function(repositories) {
            var selects = ['#github_default_repository', '#github-repository', '#github-link-repository'];
            
            $.each(selects, function(i, selectId) {
                var $select = $(selectId);
                if ($select.length === 0) return;
                
                var currentValue = $select.val();
                $select.empty().append('<option value="">Select Repository</option>');
                
                $.each(repositories, function(i, repo) {
                    if (repo.has_issues) {
                        var selected = repo.full_name === currentValue ? 'selected' : '';
                        $select.append('<option value="' + repo.full_name + '" ' + selected + '>' + repo.full_name + '</option>');
                    }
                });
            });
        },

        // Handle repository change in settings
        onRepositoryChange: function() {
            var repository = $(this).val();
            if (repository) {
                GitHub.loadLabelMappings(repository);
                $('#label-mapping-section').show();
            } else {
                $('#label-mapping-section').hide();
            }
        },

        // Load label mappings
        loadLabelMappings: function(repository) {
            $.ajax({
                url: '/github/label-mappings',
                type: 'GET',
                data: { repository: repository },
                success: function(response) {
                    if (response.status === 'success') {
                        GitHub.renderLabelMappings(response.data);
                    }
                }
            });
        },

        // Render label mappings
        renderLabelMappings: function(mappings) {
            var $container = $('#label-mappings-container');
            $container.empty();

            if (mappings.length === 0) {
                $container.html('<p class="text-muted">No label mappings configured</p>');
                return;
            }

            $.each(mappings, function(i, mapping) {
                GitHub.addLabelMappingRow(mapping);
            });
        },

        // Add label mapping row
        addLabelMappingRow: function(mapping) {
            mapping = mapping || {};
            
            var html = '<div class="label-mapping-row">' +
                '<input type="text" class="form-control" name="freescout_tag" placeholder="FreeScout Tag" value="' + (mapping.freescout_tag || '') + '">' +
                '<span>→</span>' +
                '<input type="text" class="form-control" name="github_label" placeholder="GitHub Label" value="' + (mapping.github_label || '') + '">' +
                '<input type="number" class="form-control" name="confidence_threshold" placeholder="0.80" value="' + (mapping.confidence_threshold || 0.80) + '" min="0" max="1" step="0.01">' +
                '<button type="button" class="btn btn-danger btn-sm remove-mapping">' +
                    '<i class="fa fa-trash"></i>' +
                '</button>' +
            '</div>';

            $('#label-mappings-container').append(html);
        },

        // Add label mapping
        addLabelMapping: function(e) {
            e.preventDefault();
            GitHub.addLabelMappingRow();
        },

        // Remove label mapping
        removeLabelMapping: function(e) {
            e.preventDefault();
            $(this).closest('.label-mapping-row').remove();
        },

        // Handle issue actions
        handleIssueAction: function(e) {
            e.preventDefault();
            var $link = $(this);
            var action = $link.data('action');
            var issueId = $link.data('issue-id');
            
            switch (action) {
                case 'unlink':
                    if (confirm('Are you sure you want to unlink this issue?')) {
                        GitHub.unlinkIssue(issueId);
                    }
                    break;
                case 'refresh':
                    GitHub.refreshIssue(issueId);
                    break;
            }
        },

        // Unlink issue
        unlinkIssue: function(issueId) {
            $.ajax({
                url: '/github/unlink-issue',
                type: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    conversation_id: GitHub.getConversationId(),
                    issue_id: issueId
                },
                success: function(response) {
                    if (response.status === 'success') {
                        GitHub.showAlert('success', response.message);
                        GitHub.refreshSidebar();
                    } else {
                        GitHub.showAlert('error', response.message);
                    }
                },
                error: function(xhr) {
                    var response = xhr.responseJSON || {message: 'An error occurred'};
                    GitHub.showAlert('error', response.message);
                }
            });
        },

        // Refresh issue
        refreshIssue: function(issueId) {
            var $icon = $('[data-issue-id="' + issueId + '"]').find('.fa-refresh');
            $icon.addClass('fa-spin');
            
            $.ajax({
                url: '/github/issue-details/' + issueId,
                type: 'GET',
                success: function(response) {
                    if (response.status === 'success') {
                        GitHub.refreshSidebar();
                    }
                },
                complete: function() {
                    $icon.removeClass('fa-spin');
                }
            });
        },

        // Modal show handlers
        onCreateModalShow: function() {
            GitHub.loadRepositories();
            $('#github-create-issue-form')[0].reset();
            if ($('#github-auto-generate').is(':checked')) {
                GitHub.generateIssueContent();
            }
        },

        onLinkModalShow: function() {
            GitHub.loadRepositories();
            $('#github-link-issue-form')[0].reset();
            $('#github-search-results').hide();
        },

        // Create repository change
        onCreateRepositoryChange: function() {
            var repository = $(this).val();
            if (repository) {
                GitHub.loadRepositoryLabels(repository);
            }
        },

        // Auto-generate change
        onAutoGenerateChange: function() {
            if ($(this).is(':checked')) {
                GitHub.generateIssueContent();
            }
        },

        // Load repository labels
        loadRepositoryLabels: function(repository) {
            $.ajax({
                url: '/github/labels/' + encodeURIComponent(repository),
                type: 'GET',
                success: function(response) {
                    if (response.status === 'success') {
                        GitHub.populateLabelsSelect(response.data);
                    }
                }
            });
        },

        // Populate labels select
        populateLabelsSelect: function(labels) {
            var $select = $('#github-issue-labels');
            $select.empty();
            
            $.each(labels, function(i, label) {
                $select.append('<option value="' + label.name + '">' + label.name + '</option>');
            });

            // Refresh select2 if available
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.trigger('change');
            }
        },

        // Search issues
        searchIssues: function() {
            var repository = $('#github-link-repository').val();
            var query = $('#github-issue-search').val();
            
            if (repository && query.length >= GitHub.config.searchMinLength) {
                GitHub.performIssueSearch(repository, query);
            } else {
                $('#github-search-results').hide();
            }
        },

        // Perform issue search
        performIssueSearch: function(repository, query) {
            $.ajax({
                url: '/github/search-issues',
                type: 'POST',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    repository: repository,
                    query: query,
                    per_page: GitHub.config.maxSearchResults
                },
                success: function(response) {
                    if (response.status === 'success') {
                        GitHub.displaySearchResults(response.data);
                    }
                }
            });
        },

        // Display search results
        displaySearchResults: function(issues) {
            var $container = $('#github-search-results-list');
            $container.empty();
            
            if (issues.length === 0) {
                $container.html('<p class="text-muted">No issues found</p>');
            } else {
                $.each(issues, function(i, issue) {
                    var badgeClass = issue.state === 'open' ? 'success' : 'secondary';
                    var html = '<div class="github-search-result-item" data-issue-number="' + issue.number + '">' +
                        '<div class="github-search-result-number">#' + issue.number + '</div>' +
                        '<div class="github-search-result-title">' + issue.title + '</div>' +
                        '<div class="github-search-result-meta">' +
                            '<span class="badge badge-' + badgeClass + '">' + issue.state + '</span>' +
                            ' • Updated ' + GitHub.formatDate(issue.updated_at) +
                        '</div>' +
                    '</div>';
                    $container.append(html);
                });
            }
            
            $('#github-search-results').show();
        },

        // Select search result
        selectSearchResult: function() {
            var issueNumber = $(this).data('issue-number');
            $('#github-issue-number').val(issueNumber);
            $('#github-search-results').hide();
        },

        // Generate issue content
        generateIssueContent: function() {
            // Basic content generation - can be enhanced with AI
            var subject = GitHub.getConversationSubject();
            if (subject && !$('#github-issue-title').val()) {
                $('#github-issue-title').val(subject);
            }
        },

        // Create issue
        createIssue: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var formData = $('#github-create-issue-form').serialize();
            
            GitHub.setButtonLoading($btn, true);
            
            $.ajax({
                url: '/github/create-issue',
                type: 'POST',
                data: formData + '&_token=' + $('meta[name="csrf-token"]').attr('content'),
                success: function(response) {
                    if (response.status === 'success') {
                        $('#github-create-issue-modal').modal('hide');
                        GitHub.showAlert('success', response.message);
                        GitHub.refreshSidebar();
                    } else {
                        GitHub.showAlert('error', response.message);
                    }
                },
                error: function(xhr) {
                    var response = xhr.responseJSON || {message: 'An error occurred'};
                    GitHub.showAlert('error', response.message);
                },
                complete: function() {
                    GitHub.setButtonLoading($btn, false);
                }
            });
        },

        // Link issue
        linkIssue: function(e) {
            e.preventDefault();
            var $btn = $(this);
            var formData = $('#github-link-issue-form').serialize();
            
            GitHub.setButtonLoading($btn, true);
            
            $.ajax({
                url: '/github/link-issue',
                type: 'POST',
                data: formData + '&_token=' + $('meta[name="csrf-token"]').attr('content'),
                success: function(response) {
                    if (response.status === 'success') {
                        $('#github-link-issue-modal').modal('hide');
                        GitHub.showAlert('success', response.message);
                        GitHub.refreshSidebar();
                    } else {
                        GitHub.showAlert('error', response.message);
                    }
                },
                error: function(xhr) {
                    var response = xhr.responseJSON || {message: 'An error occurred'};
                    GitHub.showAlert('error', response.message);
                },
                complete: function() {
                    GitHub.setButtonLoading($btn, false);
                }
            });
        },

        // Utility functions
        setButtonLoading: function($btn, loading) {
            var $icon = $btn.find('.fa').first();
            var originalIcon = $icon.data('original-icon') || $icon.attr('class');
            
            if (loading) {
                $icon.data('original-icon', originalIcon);
                $icon.attr('class', 'fa fa-spinner fa-spin');
                $btn.prop('disabled', true);
            } else {
                $icon.attr('class', originalIcon);
                $btn.prop('disabled', false);
            }
        },

        showAlert: function(type, message) {
            // Use FreeScout's notification system if available
            if (typeof showFloatingAlert === 'function') {
                showFloatingAlert(type, message);
            } else {
                // Fallback to console
                console.log(type.toUpperCase() + ':', message);
            }
        },

        showConnectionResult: function(response) {
            var alertClass = response.status === 'success' ? 'alert-success' : 'alert-danger';
            var icon = response.status === 'success' ? 'fa-check-circle' : 'fa-times-circle';
            
            $('#connection-result').html(
                '<div class="alert ' + alertClass + '">' +
                    '<i class="fa ' + icon + '"></i> ' + response.message +
                '</div>'
            );
            
            $('#connection-status-modal').modal('show');
        },

        refreshSidebar: function() {
            // Reload the page to refresh the sidebar
            // In a real implementation, this could be more sophisticated
            window.location.reload();
        },

        getConversationId: function() {
            return $('input[name="conversation_id"]').val() || 
                   $('meta[name="conversation-id"]').attr('content');
        },

        getConversationSubject: function() {
            return $('meta[name="conversation-subject"]').attr('content') || 
                   $('.conversation-subject').text();
        },

        formatDate: function(dateString) {
            return new Date(dateString).toLocaleDateString();
        },

        debounce: function(func, wait) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    func.apply(context, args);
                }, wait);
            };
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        GitHub.init();
    });

})(jQuery);