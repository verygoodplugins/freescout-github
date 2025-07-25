<div class="row-container">
	<div class="jira-link-issue-container">
		<form class="row jira-search-form">
			<div class="col-sm-3">
				<div class="form-group">
					<button class="btn btn-primary jira-link-issue-switch" type="button">{{ __('New Issue') }}</button>
				</div>
			</div>
			<div class="col-sm-9">
				<div class="form-group">
					<div class="input-group">
				        <input type="text" class="form-control jira-search-q" name="q">
				        <span class="input-group-btn">
				            <button class="btn btn-default jira-search-issue" type="button" data-loading-text="{{ __('Search') }}…" data-text-link="{{ __('Link Issue') }}">{{ __('Search') }}</button>
				        </span>
				    </div>
				    <div class="jira-not-found form-help hidden">{{ __('No issues found') }}</div>
				</div>
			</div>
		</form>
		<div class="jira-remote-issues margin-top-10">
		</div>
	</div>
	<div class="jira-link-issue-container hidden">
		<form class="form-horizontal jira-issue-form">

            <div class="form-group">
                <div class="col-sm-8 col-sm-offset-2">
	                <button type="button" class="btn btn-bordered jira-link-issue-switch"><i class="glyphicon glyphicon-chevron-left jira-link-issue-back"></i></button>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-2 control-label">{{ __('Project') }}</label>
                <div class="col-sm-8">
                    <select class="form-control jira-project" name="project" required autofocus>
                    	<option value=""></option>
                        @foreach ($projects as $project)
                        	<option value="{{ $project['id'] }}">{{ $project['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-2 control-label">{{ __('Type') }}</label>
                <div class="col-sm-8">
                    <select class="form-control jira-issue-type" name="type" required autofocus disabled="disabled">
                    	<option value=""></option>
                        @foreach ($projects as $project)
                        	@if (!empty($project['issueTypes']))
		                        @foreach ($project['issueTypes'] as $type)
		                        	<option value="{{ $type['id'] }}" data-project-key="{{ $project['id'] }}">{{ $type['name'] }}</option>
		                        @endforeach
		                    @endif
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-2 control-label">{{ __('Summary') }}</label>
                <div class="col-sm-8">
                    <input type="text" class="form-control" name="summary" required autofocus />
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-2 control-label">{{ __('Description') }}</label>
                <div class="col-sm-8">
                    <textarea class="form-control" name="description" rows="4" placeholder="{{ __('(optional)') }}"></textarea>
                </div>
            </div>

            {{--<div class="form-group">
                <label class="col-sm-2 control-label">{{ __('Reporter') }}</label>
                <div class="col-sm-8">
                    <select class="form-control" name="reporter" required autofocus disabled="disabled">
                        
                    </select>
                </div>
            </div>--}}

            <div class="form-group">
                <div class="col-sm-8 col-sm-offset-2">
	                <button type="submit" class="btn btn-primary jira-create-issue" data-loading-text="{{ __('Create Issue') }}…">
	                    {{ __('Create Issue') }}
	                </button>
                </div>
            </div>

		</form>
	</div>
</div>