/**
 * Module's JavaScript.
 */

function jiraInitLinkIssue()
{
	$(document).ready(function() {

		$(".jira-search-form:visible:first").submit(function(e){
			jiraSearch($(".jira-search-issue:visible:first"));
			return false;
		});

		$(".jira-search-issue:visible:first").click(function(e){
			jiraSearch($(this));
		});

		$(".modal:visible .jira-link-issue-switch").click(function(e){
			$('.jira-link-issue-container').toggleClass('hidden');
		});

		$(".modal:visible .jira-project:first").change(function(e){
			var project_key = $(this).val();
			if (project_key) {
				$('.jira-issue-type:visible:first')
					.removeAttr('disabled')
					.children('option[value!=""]').hide()
					.filter('option[data-project-key="'+project_key+'"]').show();
			} else {
				$('.jira-issue-type:visible:first').attr('disabled', 'disabled');
			}
		});

		$(".modal:visible .jira-issue-form:first").submit(function(e){
			alert('preventDefault');
			e.preventDefault();
		});

		$(".modal:visible .jira-create-issue:first").click(function(e){
			var button = $(this);
			button.button('loading');

			data = new FormData();
			//var files = $('#file')[0].files;
	        //if (files.length > 0 ){
	        //   fd.append('file',files[0]);
	        //}
	        var form = $('.jira-issue-form:visible:first').serializeArray();
	        for (var field in form) {
	        	data.append(form[field].name, form[field].value);
	        }
	        data.append('action', 'create_issue');
	        data.append('conversation_id', getGlobalAttr('conversation_id'));

			fsAjax(
				data, 
				laroute.route('jira.ajax'), 
				function(response) {
					button.button('reset');
					if (isAjaxSuccess(response)) {
						window.location.href = '';
					} else {
						showAjaxError(response);
					}
				}, true,
				function(response) {
					showFloatingAlert('error', Lang.get("messages.ajax_error"));
					ajaxFinish();
				}, {
					cache: false,
					contentType: false,
					processData: false
					//type: 'POST'
				}
			);
			return false;
		});
	});
}

function jiraSearch(button)
{
	var container = $('.jira-remote-issues:visible:first');
	var input = $(".jira-search-q:visible:first");
	var not_found_text = $(".jira-not-found:first");

	button.button('loading');
	input.attr('disabled', 'disabled');
	not_found_text.addClass('hidden');
	container.html('');

	fsAjax(
		{
			action: 'search',
			q: input.val()
		}, 
		laroute.route('jira.ajax'), 
		function(response) {
			button.button('reset');
			input.removeAttr('disabled');
			if (isAjaxSuccess(response)) {
				var html = '';
				var text_link_issue = button.attr('data-text-link');
				if (response.issues.length) {
					for (var i in response.issues) {
						var issue = response.issues[i];
						
						html += '<div class="row jira-issue-search-item">';
						html += '<div class="col-sm-9">';
						html += '<img src="'+htmlEscape(issue.issuetype.iconUrl)+'" class="jira-issuetype-icon" title="'+htmlEscape(issue.issuetype.name)+'"/> ';
						html += '<a href="'+issue.url+'" target="_blank" class="text-large">'+issue.key+' - <span class="jira-issue-search-summary">'+htmlEscape(issue.summary)+'</span></a>';
						html += ' <small class="jira-status-name">('+htmlEscape(issue.status.name)+')</small>';
						html += '<p>'+htmlEscape(issue.description)+'</p>';
						html += '</div>';
						html += '<div class="col-sm-3">';
						html += '<button type="button" class="btn btn-xs btn-default jira-btn-link-issue" data-loading-text="'+text_link_issue+'…" data-issue-key="'+issue.key+'" data-issue-type="'+issue.issuetype.id+'" data-issue-status="'+issue.status.id+'">'+text_link_issue+'</button>';
						html += '</div>';
						html += '</div>';
						if (i != response.issues.length-1) {
							html += '<hr/>';
						}
					}
				} else {
					not_found_text.removeClass('hidden');
				}
				container.html(html);
			} else {
				container.html('');
				showAjaxError(response);
			}

			// Listeners
			$(".jira-btn-link-issue").click(function(e){
				var button = $(this);
				button.button('loading');

				fsAjax(
					{
						action: 'link',
						conversation_id: getGlobalAttr('conversation_id'),
						issue_key: button.attr('data-issue-key'),
						issue_type: button.attr('data-issue-type'),
						issue_status: button.attr('data-issue-status'),
						issue_summary: button.parents('.jira-issue-search-item:first').children().find('.jira-issue-search-summary:first').html()
					}, 
					laroute.route('jira.ajax'), 
					function(response) {
						button.button('reset');
						if (isAjaxSuccess(response)) {
							window.location.href = '';
							//$('.modal').modal('hide');
						} else {
							showAjaxError(response);
						}
					}, true
				);
			});
		}, true
	);
}

function jiraInit()
{
	$(document).ready(function() {

		$(".jira-remove").click(function(e){
			var button = $(this);
			var container = button.parents('li:first');
			container.hide();
			fsAjax(
				{
					action: 'unlink_issue',
					conversation_id: getGlobalAttr('conversation_id'),
					jira_issue_id: container.attr('data-jira-issue-id'),
				},
				laroute.route('jira.ajax'), 
				function(response) {
					button.button('reset');
					if (isAjaxSuccess(response)) {
						
					} else {
						showAjaxError(response);
						container.show();
					}
				}, true,
				function(response) {
					showFloatingAlert('error', Lang.get("messages.ajax_error"));
					ajaxFinish();
					container.show();
				}
			);
		});
	});
}