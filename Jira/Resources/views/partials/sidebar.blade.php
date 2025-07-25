<div class="conv-sidebar-block">
    <div class="panel-group accordion accordion-empty">
        <div class="panel panel-default" id="jira-sidebar">
			<div class="panel-heading">
			    <h4 class="panel-title">
			        <a data-toggle="collapse" href=".jira-collapse-sidebar">
			            Jira
			            <b class="caret"></b>
			        </a>
			    </h4>
			</div>
			<div class="jira-collapse-sidebar panel-collapse collapse in">
			    <div class="panel-body">
			        <div class="sidebar-block-header2"><strong>Jira</strong> (<a data-toggle="collapse" href=".jira-collapse-sidebar">{{ __('close') }}</a>)</div>
			        
		            @if (count($issues)) 
			            <ul class="sidebar-block-list jira-sidebar">
		                    @foreach($issues as $issue)
	                            <li data-jira-issue-id="{{ $issue->id }}">
	                                <span class="pull-right jira-remove"><i class="glyphicon glyphicon-remove"></i></span><img src="{{ $issue->getTypeIcon() }}" data-toggle="tooltip" title="{{ $issue->getTypeName() }}" />&nbsp;<a href="{{ $issue->getUrl() }}" target="_blank">{{ $issue->getTitle() }}</a> <small class="jira-status-name">@if ($issue->status)({{ $issue->getStatusName() }})@endif</small>
	                            </li>
		                    @endforeach
	                    </ul>
					@endif
			   
			        <div class="margin-top-10">
			            <a href="{{ route('jira.ajax_html', ['action' => 'link_issue']) }}" data-trigger="modal" data-modal-title="{{ __('Link Jira Issue') }}" data-modal-no-footer="true" data-modal-on-show="jiraInitLinkIssue" class="btn btn-default btn-block" id="jira-link-issue"><small class="glyphicon glyphicon-link"></small> {{ __("Link Jira Issue") }}</a>
			        </div>
				   
			    </div>
			</div>

        </div>
    </div>
</div>

@section('javascript')
    @parent
    jiraInit();
@endsection