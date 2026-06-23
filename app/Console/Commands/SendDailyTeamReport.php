<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\Helper;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\UserProductivitySummary;
use App\Models\Project;
use App\Models\Task;
use App\Models\Issue;
use Carbon\Carbon;

class SendDailyTeamReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:daily-team {--force : Force the report to run ignoring the scheduled time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily team report to owners';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $owners = User::where('parent_user_id', 0)->get();
        $isForce = $this->option('force');

        foreach ($owners as $owner) {
            $settings = $owner->settings ?? [];
            if (is_string($settings)) {
                $settings = json_decode($settings, true);
            }
            
            $timezone = $settings['timezone'] ?? config('app.timezone', 'UTC');
            $reportTime = $settings['report_time'] ?? '17:00';
            $targetEmails = $settings['report_emails'] ?? [$owner->email];
            
            if (!is_array($targetEmails)) {
                $targetEmails = [$owner->email];
            }
            
            $currentTime = Carbon::now()->setTimezone($timezone);
            
            if (!$isForce && $currentTime->format('H:i') !== $reportTime) {
                continue;
            }

            // Since report runs at the end of the day, "today" in the owner's timezone is what we want
            $today = $currentTime->copy()->startOfDay();
            
            $teamMembers = User::where('parent_user_id', $owner->id)->orWhere('id', $owner->id)->get();
            if ($teamMembers->isEmpty()) {
                continue;
            }

            $teamUserIds = $teamMembers->pluck('id')->toArray();

            // Total Team Hours Tracked Today
            $totalTeamHoursSeconds = UserProductivitySummary::whereIn('user_id', $teamUserIds)
                ->whereDate('date', $today)
                ->sum('total_seconds');
            $totalTeamHours = Helper::convertSecondsInReadableFormat($totalTeamHoursSeconds);

            $employeesData = [];
            foreach ($teamMembers as $employee) {
                // Total Hours Worked
                $totalHoursSeconds = UserProductivitySummary::where('user_id', $employee->id)
                    ->whereDate('date', $today)
                    ->sum('total_seconds');

                $totalHoursWorked = Helper::convertSecondsInReadableFormat($totalHoursSeconds);

                // Logged In / Out
                $firstActivity = UserActivity::where('user_id', $employee->id)
                    ->whereDate('start_datetime', $today)
                    ->min('start_datetime');
                $lastActivity = UserActivity::where('user_id', $employee->id)
                    ->whereDate('start_datetime', $today)
                    ->max('end_datetime');

                $loggedIn = $firstActivity ? Carbon::parse($firstActivity)->setTimezone($timezone)->format('h:i A') : 'N/A';
                $loggedOut = $lastActivity ? Carbon::parse($lastActivity)->setTimezone($timezone)->format('h:i A') : 'N/A';

                $topWebsitesQuery = Helper::getTopWebsites(1, [$employee->id]);
                $topAppsQuery = Helper::getTopProcesses(1, [$employee->id]);

                $topWebsites = [];
                foreach ($topWebsitesQuery->take(2) as $w) {
                    $topWebsites[] = $w->process_name . ' (' . $w->time_used_human . ')';
                }

                $topApps = [];
                foreach ($topAppsQuery->take(2) as $a) {
                    $topApps[] = $a->process_name . ' (' . $a->time_used_human . ')';
                }

                $employeesData[] = [
                    'name' => $employee->name,
                    'total_hours_seconds' => $totalHoursSeconds,
                    'total_hours' => $totalHoursWorked,
                    'logged_in' => $loggedIn,
                    'logged_out' => $loggedOut,
                    'top_websites' => empty($topWebsites) ? 'None' : implode(', ', $topWebsites),
                    'top_apps' => empty($topApps) ? 'None' : implode(', ', $topApps),
                ];
            }

            // Sort by most hours worked
            usort($employeesData, function($a, $b) {
                return $b['total_hours_seconds'] <=> $a['total_hours_seconds'];
            });

            // Project data
            $allOwnerIds = $teamMembers->pluck('id')->push($owner->id)->toArray();
            $activeProjectIds = UserActivity::whereIn('user_id', $allOwnerIds)
                ->whereDate('start_datetime', $today)
                ->whereNotNull('project_id')
                ->distinct('project_id')
                ->pluck('project_id')->toArray();

            // also consider projects where tasks/issues were updated today
            $projectIdsWithTaskActivity = Task::whereDate('completed_at', $today)
                ->whereIn('project_id', function($q) use ($allOwnerIds) {
                    $q->select('project_id')->from('project_members')->whereIn('user_id', $allOwnerIds);
                })->pluck('project_id')->toArray();

            $projectIdsWithIssueActivity = Issue::whereDate('resolved_at', $today)
                ->whereIn('project_id', function($q) use ($allOwnerIds) {
                    $q->select('project_id')->from('project_members')->whereIn('user_id', $allOwnerIds);
                })->pluck('project_id')->toArray();

            $allActiveProjectIds = array_unique(array_merge($activeProjectIds, $projectIdsWithTaskActivity, $projectIdsWithIssueActivity));

            $projects = Project::whereIn('id', $allActiveProjectIds)->get();
            $projectsData = [];

            foreach ($projects as $project) {
                $tasksCompletedToday = Task::where('project_id', $project->id)
                    ->where('status', 'DONE')
                    ->whereDate('completed_at', $today)
                    ->count();
                $tasksLeft = Task::where('project_id', $project->id)
                    ->where('status', '!=', 'DONE')
                    ->count();
                $issuesCompletedToday = Issue::where('project_id', $project->id)
                    ->whereIn('status', ['RESOLVED', 'CLOSED', 'DONE'])
                    ->whereDate('resolved_at', $today)
                    ->count();
                $issuesLeft = Issue::where('project_id', $project->id)
                    ->whereNotIn('status', ['RESOLVED', 'CLOSED', 'DONE'])
                    ->count();

                $projectsData[] = [
                    'name' => $project->name,
                    'tasks_completed_today' => $tasksCompletedToday,
                    'tasks_left' => $tasksLeft,
                    'issues_completed_today' => $issuesCompletedToday,
                    'issues_left' => $issuesLeft,
                ];
            }

            // Send email
            if ($totalTeamHoursSeconds > 0 || !empty($projectsData)) {
                $subject = 'Daily Team Report - ' . $today->format('M d, Y');

                $body = view('emails.daily_team_report', [
                    'reportData' => [
                        'totalTeamHours' => $totalTeamHours,
                        'employeesData' => $employeesData,
                        'projectsData' => $projectsData,
                        'ownerName' => $owner->name,
                        'date' => $today->format('M d, Y')
                    ]
                ])->render();

                $this->info("----- HTML OUTPUT START -----");
                $this->line($body);
                $this->info("----- HTML OUTPUT END -----");

                foreach ($targetEmails as $email) {
                    Helper::sendEmail($email, $subject, $body, $owner->name);
                }
            }
        }

        return Command::SUCCESS;
    }
}
