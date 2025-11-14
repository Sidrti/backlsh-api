<?php

namespace App\Http\Controllers\V1\Website;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\UserSubActivity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectReportController extends Controller
{
    public function fetchProjectReport(Request $request)
    {
        $request->validate([
            'project_id' => 'required|exists:projects,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $projectId = $request->input('project_id');
        $startDate = $request->input('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        // Verify user has access to this project
        $userId = auth()->user()->id;
        $project = Project::where('id', $projectId)
            ->where(function ($query) use ($userId) {
                $query->where('created_by', $userId)
                    ->orWhereHas('members', function ($q) use ($userId) {
                        $q->where('user_id', $userId);
                    });
            })
            ->firstOrFail();

        // 1. Total Time Invested by entire team
        $totalTimeInvested = $this->getTotalProjectTime($projectId, $startDate, $endDate);

        // 2. Count of Total Completed Tasks
        $completedTasksCount = Task::where('project_id', $projectId)
            ->where('status', 'DONE')
            ->count();

        // 3. Total Team Members assigned
        $totalTeamMembers = $this->getProjectMembersCount($projectId);

        // 4. List of all members with their time spent
        $membersList = $this->getProjectMembersWithTime($projectId, $startDate, $endDate, $totalTimeInvested);

        // 5. Task status breakdown
        $taskStatusBreakdown = $this->getTaskStatusBreakdown($projectId);

        // 6. List of all tasks with details
        $tasksList = $this->getProjectTasks($projectId, $startDate, $endDate);

        // 7. Top used applications and websites
        $topApplications = $this->getTopApplications($projectId, $startDate, $endDate);
        $topWebsites = $this->getTopWebsites($projectId, $startDate, $endDate);

        // 8. Daily time log
        $dailyTimeLog = $this->getDailyTimeLog($projectId, $startDate, $endDate);

        return response()->json([
            'status_code' => 1,
            'data' => [
                'project_info' => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'description' => $project->description,
                    'status' => $project->status,
                ],
                'total_time_invested' => $totalTimeInvested,
                'completed_tasks_count' => $completedTasksCount,
                'total_team_members' => $totalTeamMembers,
                'members_list' => $membersList,
                'task_status_breakdown' => $taskStatusBreakdown,
                'tasks_list' => $tasksList,
                'top_applications' => $topApplications,
                'top_websites' => $topWebsites,
                'daily_time_log' => $dailyTimeLog,
            ],
            'message' => 'Project report fetched successfully',
        ]);
    }

    private function getTotalProjectTime($projectId, $startDate, $endDate)
    {
        $totalSeconds = UserActivity::where('project_id', $projectId)
            // ->whereBetween('start_datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            // ->whereRaw('DATE(start_datetime) = DATE(end_datetime)')
            ->sum(DB::raw('TIMESTAMPDIFF(SECOND, start_datetime, end_datetime)'));

        return [
            'seconds' => $totalSeconds,
            'formatted' => Helper::convertSecondsInReadableFormat($totalSeconds),
        ];
    }

    private function getProjectMembersCount($projectId)
    {
        return DB::table('project_members')
            ->where('project_id', $projectId)
            ->count();
    }

    private function getProjectMembersWithTime($projectId, $startDate, $endDate, $totalTimeInvested)
    {
        $members = DB::table('project_members')
            ->join('users', 'users.id', '=', 'project_members.user_id')
            ->leftJoin('user_activities', function ($join) use ($startDate, $endDate) {
                $join->on('user_activities.user_id', '=', 'users.id')
                    ->where('user_activities.project_id', '=', DB::raw('project_members.project_id'))
                    ->whereBetween('user_activities.start_datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                    ->whereRaw('DATE(user_activities.start_datetime) = DATE(user_activities.end_datetime)');
            })
            ->where('project_members.project_id', $projectId)
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.profile_picture',
                DB::raw('COALESCE(SUM(TIMESTAMPDIFF(SECOND, user_activities.start_datetime, user_activities.end_datetime)), 0) as total_seconds')
            )
            ->groupBy('users.id', 'users.name', 'users.email', 'users.profile_picture')
            ->orderByDesc('total_seconds')
            ->get();

        return $members->map(function ($member) use ($totalTimeInvested) {
            $percentage = $totalTimeInvested['seconds'] > 0 
                ? round(($member->total_seconds / $totalTimeInvested['seconds']) * 100, 2) 
                : 0;

            return [
                'user_id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'profile_picture' => (new User(['profile_picture' => $member->profile_picture]))->profile_picture,
                'total_time_seconds' => $member->total_seconds,
                'total_time_formatted' => Helper::convertSecondsInReadableFormat($member->total_seconds),
                'percentage' => $percentage,
            ];
        })->toArray();
    }

    private function getTaskStatusBreakdown($projectId)
    {
        $totalTasks = Task::where('project_id', $projectId)->count();

        $statusCounts = Task::where('project_id', $projectId)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        $todoCount = $statusCounts->get('TODO', 0);
        $inProgressCount = $statusCounts->get('IN_PROGRESS', 0);
        $doneCount = $statusCounts->get('DONE', 0);

        return [
            'total_tasks' => $totalTasks,
            'todo' => [
                'count' => $todoCount,
                'percentage' => $totalTasks > 0 ? round(($todoCount / $totalTasks) * 100, 2) : 0,
            ],
            'in_progress' => [
                'count' => $inProgressCount,
                'percentage' => $totalTasks > 0 ? round(($inProgressCount / $totalTasks) * 100, 2) : 0,
            ],
            'done' => [
                'count' => $doneCount,
                'percentage' => $totalTasks > 0 ? round(($doneCount / $totalTasks) * 100, 2) : 0,
            ],
        ];
    }

    private function getProjectTasks($projectId, $startDate, $endDate)
    {
        $tasks = Task::where('tasks.project_id', $projectId)
            ->leftJoin('users', 'users.id', '=', 'tasks.assignee_id')
            ->leftJoin('user_activities', function ($join) use ($startDate, $endDate) {
                $join->on('user_activities.task_id', '=', 'tasks.id')
                    ->whereBetween('user_activities.start_datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                    ->whereRaw('DATE(user_activities.start_datetime) = DATE(user_activities.end_datetime)');
            })
            ->select(
                'tasks.id',
                'tasks.name',
                'tasks.description',
                'tasks.status',
                'tasks.priority',
                'tasks.due_date',
                'tasks.updated_at',
                'users.id as assignee_id',
                'users.name as assignee_name',
                'users.profile_picture as assignee_profile_picture',
                DB::raw('COALESCE(SUM(TIMESTAMPDIFF(SECOND, user_activities.start_datetime, user_activities.end_datetime)), 0) as total_seconds')
            )
            ->groupBy(
                'tasks.id',
                'tasks.name',
                'tasks.description',
                'tasks.status',
                'tasks.priority',
                'tasks.due_date',
                'tasks.updated_at',
                'users.id',
                'users.name',
                'users.profile_picture'
            )
            ->orderBy('tasks.updated_at', 'desc')
            ->get();

        return $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'name' => $task->name,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date,
                'last_updated' => $task->updated_at ? Carbon::parse($task->updated_at)->diffForHumans() : null,
                'assignee' => $task->assignee_id ? [
                    'id' => $task->assignee_id,
                    'name' => $task->assignee_name,
                    'profile_picture' => (new User(['profile_picture' => $task->assignee_profile_picture]))->profile_picture,
                ] : null,
                'total_time_seconds' => $task->total_seconds,
                'total_time_formatted' => Helper::convertSecondsInReadableFormat($task->total_seconds),
            ];
        })->toArray();
    }

    private function getTopApplications($projectId, $startDate, $endDate)
    {
        $totalSeconds = UserActivity::where('project_id', $projectId)
            ->sum(DB::raw('TIMESTAMPDIFF(SECOND, start_datetime, end_datetime)'));

        $applications = UserActivity::where('user_activities.project_id', $projectId)
            ->join('processes', 'processes.id', '=', 'user_activities.process_id')
            ->whereBetween('user_activities.start_datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->whereRaw('DATE(user_activities.start_datetime) = DATE(user_activities.end_datetime)')
            ->whereNotIn('processes.type', ['BROWSER', 'WEBSITE'])
            ->whereNotIn('processes.process_name', ['-1', 'LockApp', 'Idle'])
            ->select(
                'processes.id',
                'processes.process_name',
                'processes.icon',
                'processes.type',
                'user_activities.productivity_status',
                DB::raw('SUM(TIMESTAMPDIFF(SECOND, user_activities.start_datetime, user_activities.end_datetime)) as total_seconds')
            )
            ->groupBy('processes.id', 'processes.process_name', 'processes.icon', 'processes.type','user_activities.productivity_status')
            ->orderByDesc('total_seconds')
            ->limit(5)
            ->get();

        return $applications->map(function ($app) use ($totalSeconds) {
            $percentage = $totalSeconds > 0 ? round(($app->total_seconds / $totalSeconds) * 100, 2) : 0;

            return [
                'id' => $app->id,
                'process_name' => $app->process_name,
                'icon_url' => asset('storage/' . ($app->icon ?? config('app.process_default_image'))),
                'type' => $app->type,
                'total_time_seconds' => $app->total_seconds,
                'total_time_formatted' => Helper::convertSecondsInReadableFormat($app->total_seconds),
                'productivity_status' => $app->productivity_status,
                'percentage' => $percentage,
            ];
        })->toArray();
    }

    private function getTopWebsites($projectId, $startDate, $endDate)
    {
        $totalSeconds = UserSubActivity::join('user_activities', 'user_activities.id', '=', 'user_sub_activities.user_activity_id')
            ->where('user_activities.project_id', $projectId)
            ->whereBetween('user_sub_activities.start_datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->whereRaw('DATE(user_sub_activities.start_datetime) = DATE(user_sub_activities.end_datetime)')
            ->sum(DB::raw('TIMESTAMPDIFF(SECOND, user_sub_activities.start_datetime, user_sub_activities.end_datetime)'));

        $websites = UserSubActivity::join('user_activities', 'user_activities.id', '=', 'user_sub_activities.user_activity_id')
            ->join('processes', 'processes.id', '=', 'user_sub_activities.process_id')
            ->where('user_activities.project_id', $projectId)
            ->whereBetween('user_sub_activities.start_datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->whereRaw('DATE(user_sub_activities.start_datetime) = DATE(user_sub_activities.end_datetime)')
            ->whereNotIn('processes.process_name', ['-1', 'LockApp', 'Idle'])
            ->select(
                'processes.id',
                'processes.process_name',
                'processes.icon',
                'processes.type',
                'user_sub_activities.productivity_status',
                DB::raw('SUM(TIMESTAMPDIFF(SECOND, user_sub_activities.start_datetime, user_sub_activities.end_datetime)) as total_seconds')
            )
            ->groupBy('processes.id', 'processes.process_name', 'processes.icon', 'processes.type','user_sub_activities.productivity_status')
            ->orderByDesc('total_seconds')
            ->limit(5)
            ->get();

        return $websites->map(function ($website) use ($totalSeconds) {
            $percentage = $totalSeconds > 0 ? round(($website->total_seconds / $totalSeconds) * 100, 2) : 0;

            return [
                'id' => $website->id,
                'process_name' => $website->process_name,
                'icon_url' => asset('storage/' . ($website->icon ?? config('app.web_default_image'))),
                'type' => $website->type,
                'total_time_seconds' => $website->total_seconds,
                'total_time_formatted' => Helper::convertSecondsInReadableFormat($website->total_seconds),
                'productivity_status' => $website->productivity_status,
                'percentage' => $percentage,
            ];
        })->toArray();
    }

    private function getDailyTimeLog($projectId, $startDate, $endDate)
    {
        // Get daily totals
        $dailyTotals = UserActivity::where('project_id', $projectId)
            ->whereBetween('start_datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->whereRaw('DATE(start_datetime) = DATE(end_datetime)')
            ->select(
                DB::raw('DATE(start_datetime) as date'),
                DB::raw('SUM(TIMESTAMPDIFF(SECOND, start_datetime, end_datetime)) as total_seconds')
            )
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        $result = [];

        foreach ($dailyTotals as $daily) {
            $users = [];
            // Get users who worked on this date
            $users = UserActivity::where('user_activities.project_id', $projectId)
                ->whereDate('user_activities.start_datetime', $daily->date)
                ->whereRaw('DATE(user_activities.start_datetime) = DATE(user_activities.end_datetime)')
                ->join('users', 'users.id', '=', 'user_activities.user_id')
                ->select(
                    'users.id',
                    'users.name',
                    'users.profile_picture',
                    DB::raw('SUM(TIMESTAMPDIFF(SECOND, user_activities.start_datetime, user_activities.end_datetime)) as user_seconds'),
                    DB::raw('SUM(CASE WHEN user_activities.productivity_status = "PRODUCTIVE" THEN TIMESTAMPDIFF(SECOND, user_activities.start_datetime, user_activities.end_datetime) ELSE 0 END) as productive_seconds'),
                    DB::raw('SUM(CASE WHEN user_activities.productivity_status = "NONPRODUCTIVE" THEN TIMESTAMPDIFF(SECOND, user_activities.start_datetime, user_activities.end_datetime) ELSE 0 END) as nonproductive_seconds'),
                    DB::raw('SUM(CASE WHEN user_activities.productivity_status = "NEUTRAL" THEN TIMESTAMPDIFF(SECOND, user_activities.start_datetime, user_activities.end_datetime) ELSE 0 END) as neutral_seconds')
                )
                ->groupBy('users.id', 'users.name', 'users.profile_picture')
                ->orderByDesc('user_seconds')
                ->get()
                ->map(function ($user) {
                    // Determine overall productivity status based on which status has the most time
                    $productivityStatus = 'NEUTRAL';
                    if ($user->productive_seconds > $user->nonproductive_seconds && $user->productive_seconds > $user->neutral_seconds) {
                        $productivityStatus = 'PRODUCTIVE';
                    } elseif ($user->nonproductive_seconds > $user->productive_seconds && $user->nonproductive_seconds > $user->neutral_seconds) {
                        $productivityStatus = 'NONPRODUCTIVE';
                    }

                    return [
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'profile_picture' => (new User(['profile_picture' => $user->profile_picture]))->profile_picture,
                        'time_spent_seconds' => $user->user_seconds,
                        'time_spent_formatted' => Helper::convertSecondsInReadableFormat($user->user_seconds),
                        'productivity_status' => $productivityStatus
                    ];
                });

            $result[] = [
                'date' => Carbon::parse($daily->date)->format('Y-m-d'),
                'date_formatted' => Carbon::parse($daily->date)->format('d M, Y'),
                'total_time_seconds' => $daily->total_seconds,
                'total_time_formatted' => Helper::convertSecondsInReadableFormat($daily->total_seconds),
                'users' => $users->toArray(),
            ];
        }

        return $result;
    }
}