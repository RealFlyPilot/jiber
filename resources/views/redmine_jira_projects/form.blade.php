@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="col-sm-offset-2 col-sm-8">
            <div class="panel panel-default">
                <div class="panel-body">
                    <h1>Update Redmine/Jira Project</h1>

                    <form action="{{ action('RedmineJiraProjectsController@update', ['project' => $project->id]) }}" method="post">
                        <input name="_method" type="hidden" value="PUT"/>
                        {{ csrf_field() }}

                        <fieldset class="form-group">
                            <label for="redmine_name">Redmine Project name</label>
                            <input type="text" name="redmine_name" readonly id="redmine_name" class="form-control" @if($project)value="{{ $project->redmine_name }}"@endif>
                        </fieldset>

                        <fieldset class="form-group">
                            <label for="jira_name">Jira Project name</label>
                            <input type="text" name="jira_name" id="jira_name" class="form-control" @if($project)value="{{ $project->jira_name }}"@endif>
                        </fieldset>

                        <fieldset class="form-group">
                            <label for="content">Content</label>
                            <textarea name="content" id="content" class="form-control">@if($project){{ $project->content }}@endif</textarea>
			    <p>This will be added at the end of the ticket's description when it's created.</p>
                        </fieldset>

                        <button type="submit" class="btn btn-primary">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
