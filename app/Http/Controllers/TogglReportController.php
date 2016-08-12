<?php

/**
 * Copyright 2016 Thaissa Mendes
 *
 * This file is part of Jiber.
 *
 * Jiber is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jiber is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jiber. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Control connection with Toggl, using Toggl Client and Reports Client
 *
 * @author Thaissa Mendes <thaissa.mendes@gmail.com>
 * @since July 30, 2016
 * @version 0.1
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;

use App\TogglClient;
use App\TogglProject;
use App\TogglReport;
use App\TogglTask;
use App\TogglTimeEntry;
use App\TogglWorkspace;

class TogglReportController extends TogglController
{
	/**
	 * List all reports on system, and show form to create a new report
	 */
  function index(Request $request)
  {
		$reports    =    TogglReport::getAllByUserID($request->user()->id, 'id', 'DESC');
		$workspaces = TogglWorkspace::getAllByUserID($request->user()->id);
		$clients    =    TogglClient::getAllByUserID($request->user()->id);

    return view('toggl_report.index', [
      'reports'    => $reports,
      'workspaces' => $workspaces,
      'clients'    => $clients
    ]);
  }

	/**
	 * Save report, and all entries
	 */
  function save(Request $request)
  {
		set_time_limit(0);

    $toggl_client = $this->toggl_connect($request);

    if (!$request->date)
		{
			$request->start_date = date('Y-m-d', strtotime('-6 days'));
    	$request->end_date   = date('Y-m-d');
		}
		else
		{
			list($start_date, $end_date) = explode(' - ', $request->date);

			$request->start_date = date('Y-m-d', strtotime($start_date));
			$request->end_date   = date('Y-m-d', strtotime($end_date));
		}

    // Save Report
    $report = new TogglReport;
    $report->user_id      = $request->user()->id;
    $report->start_date   = $request->start_date;
    $report->end_date     = $request->end_date;
    $report->client_ids   = ($request->clients  ? implode(',', $request->clients)  : null);
    $report->project_ids  = ($request->projects ? implode(',', $request->projects) : null);
    $report->save();

		// Get current user from Toggl, so we can filter time entries
    $current_user = $toggl_client->GetCurrentUser();

		// Toggl's arguments array
    $args = array(
      'user_agent'   => 'Jiber <thaissa.mendes@gmail.com>',
      'workspace_id' => (int)$request->workspace,
      'user_ids'     => $current_user['id'],
    );

    if ($request->start_date)
      $args['since'] = $request->start_date;

    if ($request->end_date)
      $args['until'] = $request->end_date;

    if ($request->clients)
      $args['client_ids'] = implode(',', $request->clients);

    $results = $this->getToggle($request, $args);

		// Toggl has a limit of time entries per page
		$pages = ceil($results['total_count'] / $results['per_page']);

		for ($page=1; $page<=$pages; $page++)
		{
			if ($page > 1)
			{
				$args['page'] = $page;
    		$results = $this->getToggle($request, $args);
			}

			foreach ($results['data'] as $_data)
			{
				$client  =  TogglClient::getByName($_data['client'] , $request->user()->id);
				$project = TogglProject::getByName($_data['project'], $request->user()->id);
				$task    =    TogglTask::getByTogglID($_data['tid'] , $request->user()->id);

				// Create Toggl Time Entry
				$entry = new TogglTimeEntry;
				$entry->toggl_id    = $_data['id'];
				$entry->user_id     = $request->user()->id;
				$entry->report_id   = $report->id;
				$entry->client_id   = $client->id;
				$entry->project_id  = ($project ? $project->id : null);
				$entry->task_id     = ($task ? $task->id : null);
				$entry->date        = date('Y-m-d', strtotime($_data['start']));
				$entry->time        = $_data['start'];
				$entry->duration    = $_data['dur'];
				$entry->description = $_data['description'];

				// If client is OneRhino, get Redmine and Jira related task IDs and save on time entry
				if ($redmine_task_id = $entry->isRedmine($request->user()->id))
				{
					$entry->redmine = $redmine_task_id;

					if ($jira_id = $entry->isJira())
					{
						$entry->jira = $jira_id;
					}
				}

				$entry->save();
			}
		}

    return redirect()->action('TogglReportController@show', [$report->id]);
  }

	/**
	 * Get Toggl time entries based on arguments
	 */
	private function getToggle(Request $request, $args)
	{
    $reports_client = $this->reports_connect($request);
    return $reports_client->details($args);
	}

	/**
	 * Show report time entries
	 */
  public function show(TogglReport $report, Request $request)
  {
		if ($report->user_id != $request->user()->id)
			abort(403, 'Unauthorized action.');

		// Empty Redmine and Jira report from session
		if ($request->session()->has('redmine.report.'.$report->id))
			$request->session()->forget('redmine.report.'.$report->id);

		if ($request->session()->has('jira.report.'.$report->id))
			$request->session()->forget('jira.report.'.$report->id);

    return view('toggl_report.show', [
      'report' => $report
    ]);
  }

	/**
	 * Remove report from database
	 */
	public function delete(TogglReport $report, Request $request)
	{
		if ($report->user_id != $request->user()->id)
			abort(403, 'Unauthorized action.');

		if (!$report->canDelete())
		{
    	$request->session()->flash('alert-danger', 'This report cannot be deleted.');
			return back()->withInput();
		}

		$report->delete();
    $request->session()->flash('alert-success', 'Report has been successfully deleted!');
		return back()->withInput();
	}
}
