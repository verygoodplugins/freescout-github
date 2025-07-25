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
            "uri": "github\/label-mappings",
            "name": "github.label_mappings"
        },
        {
            "uri": "github\/webhook",
            "name": "github.webhook"
        }
    ];

    if (typeof(laroute) != "undefined") {
        laroute.add_routes(module_routes);
    } else {
        console.log('laroute not initialized, can not add module routes:');
        console.log(module_routes);
    }
})();