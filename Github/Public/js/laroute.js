(function () {
    var module_routes = [
        {
            "uri": "github\/test-connection",
            "name": "github.test_connection"
        },
        {
            "uri": "github\/repositories",
            "name": "github.repositories"
        },
        {
            "uri": "github\/labels\/{repository}",
            "name": "github.labels"
        },
        {
            "uri": "github\/search-issues",
            "name": "github.search_issues"
        },
        {
            "uri": "github\/create-issue",
            "name": "github.create_issue"
        },
        {
            "uri": "github\/link-issue",
            "name": "github.link_issue"
        },
        {
            "uri": "github\/unlink-issue",
            "name": "github.unlink_issue"
        },
        {
            "uri": "github\/issue-details\/{id}",
            "name": "github.issue_details"
        },
        {
            "uri": "github\/refresh-issue\/{id}",
            "name": "github.refresh_issue"
        },
        {
            "uri": "github\/refresh-conversation-issues",
            "name": "github.refresh_conversation_issues"
        },
        {
            "uri": "github\/generate-content",
            "name": "github.generate_content"
        },
        {
            "uri": "github\/label-mappings",
            "name": "github.label_mappings"
        },
        {
            "uri": "github\/label-mappings",
            "name": "github.save_label_mappings"
        },
        {
            "uri": "github\/save-settings",
            "name": "github.save_settings"
        },
        {
            "uri": "github\/webhook",
            "name": "github.webhook"
        }
    ];

    if (typeof(laroute) != "undefined") {
        laroute.add_routes(module_routes);
    } else {
        // laroute not initialized, can not add module routes
    }
})();