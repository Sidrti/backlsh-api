<?php

namespace App\Http\Controllers\V1\Website;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Process;
use App\Models\UserActivity;
use App\Models\UserScreenshot;
use App\Models\UserSubActivity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function fetchUserReportsByDate(Request $request)
    {
        $request->validate([
            'user_id' => ['required','integer',
                function ($attribute, $value, $fail) {
                    if ($value != 0 && !\DB::table('users')->where('id', $value)->exists()) {
                        $fail('The selected '.$attribute.' is invalid.');
                    }
                },
            ],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'project_id' => 'nullable|integer',
        ]);
        
        $processReqOnly = $request->input('process_req_only', false);
        $subActivityReqOnly = $request->input('subactivity_req_only', false);
        $projectId = $request->input('project_id', 0);

        if($request->input('user_id') == 0) {
            return response()->json(config('dummy.report'));
        }

        $startDate = Carbon::parse(
            $request->input('start_date', Carbon::now()->subDays(7)->toDateString())
        )->startOfDay();

        $endDate = Carbon::parse(
            $request->input('end_date', Carbon::now()->toDateString())
        )->endOfDay();

        $userId = $request->input('user_id');

        if ($processReqOnly) {
            $process = $this->getProcessDataWithScreenshots($userId, $startDate, $endDate, $projectId);
            return response()->json(['dummy'=> false, 'status_code' => 1, 'data' => ['process' => $process]]);
        }
        
        if ($subActivityReqOnly) {
            $userSubActivities = $this->getSubActivityDataWithScreenshots($userId, $startDate, $endDate, $projectId);
            return response()->json(['dummy'=> false, 'status_code' => 1, 'data' => ['user_sub_activities' => $userSubActivities]]);
        }

        // Existing data
        $process = $this->getProcessDataWithScreenshots($userId, $startDate, $endDate, $projectId);
        $totalProductiveHours = Helper::calculateTotalHoursByUserId($userId, $startDate, $endDate, 'PRODUCTIVE');
        $userWorkingTrend = $this->getProductiveNonProductiveTimeByEachDay($userId, $startDate, $endDate, false);
        $userAttendance = Helper::getUserAttendance($userId, Carbon::parse($startDate), Carbon::parse($endDate));
        $totalHoursWorked = Helper::calculateTotalHoursByUserId($userId, $startDate, $endDate);
        $userSubActivities = $this->getSubActivityDataWithScreenshots($userId, $startDate, $endDate, $projectId);
        $userTimeline = $this->getUserActivitySlots($userId, $startDate, $endDate);

        // New data - Projects and Tasks - NOW USING HELPER
        $totalProjectsAssigned = Helper::getTotalProjectsAssigned($userId);
        $totalTasksAssigned = Helper::getTotalTasksAssigned($userId);
        $tasksOverdue = Helper::getTasksOverdue($userId,false);
        $projectsAssignedList = Helper::getProjectsForUser($userId, $startDate, $endDate);

        $data =  [
            'process' => $process,
            'total_productive_hours' => $totalProductiveHours,
            'user_attendance' => $userAttendance,
            'working_trend' => $userWorkingTrend,
            'total_time_worked' => $totalHoursWorked,
            'user_sub_activities' => $userSubActivities,
            'user_timeline' => $userTimeline,
            
            // New fields
            'total_projects_assigned' => $totalProjectsAssigned,
            'total_tasks_assigned' => $totalTasksAssigned,
            'tasks_overdue' => $tasksOverdue,
            'projects_assigned_list' => $projectsAssignedList,
        ];

        return response()->json(['status_code' => 1, 'data' => $data]);
    }

    private function getProcessDataWithScreenshots($userId, $startDate, $endDate, $projectId = 0)
    {
        $processData = UserActivity::join('processes', 'user_activities.process_id', '=', 'processes.id')
            ->select(
                'user_activities.process_id',
                'processes.process_name',
                'user_activities.productivity_status',
                'processes.type',
                'processes.icon',
                DB::raw('SUM(TIMESTAMPDIFF(SECOND, user_activities.start_datetime, user_activities.end_datetime)) AS total_seconds'),
            )
            ->where('user_activities.user_id', $userId)
            ->where('processes.process_name', '!=', '-1')
            ->where('processes.process_name', '!=', 'LockApp')
            ->where('processes.process_name', '!=', 'Idle')
            ->when($projectId != 0, function ($query) use ($projectId) {
                $query->where('user_activities.project_id', $projectId);
            })
            ->whereRaw("DATE(user_activities.start_datetime) BETWEEN ? AND ?", [$startDate, $endDate])
            ->whereRaw("DATE(user_activities.end_datetime) BETWEEN ? AND ?", [$startDate, $endDate])
            ->whereRaw("DATE(user_activities.start_datetime) = DATE(user_activities.end_datetime)")
            ->havingRaw('SUM(TIMESTAMPDIFF(SECOND, user_activities.start_datetime, user_activities.end_datetime)) >= 60')
            ->groupBy('user_activities.process_id', 'processes.process_name', 'user_activities.productivity_status', 'processes.type', 'processes.icon')
            ->orderByDesc('total_seconds')
            ->get();

        foreach ($processData as $item) {
            $processId = $item->process_id;

            $screenshots = UserScreenshot::whereBetween('created_at', [$startDate, $endDate])
                ->where('process_id', $processId)
                ->where('user_id', $userId)
                ->get();

            $item->icon_url = asset('storage/' . ($item->icon ?? config(('app.process_default_image'))));
            $item->total_time = Helper::convertSecondsInReadableFormat($item->total_seconds);
            $item->screenshots = $screenshots;
        }

        return $processData;
    }

    private function getSubActivityDataWithScreenshots($userId, $startDate, $endDate, $projectId = 0)
    {
        $userSubActivityData = UserSubActivity::join('processes', 'user_sub_activities.process_id', '=', 'processes.id')
            ->select(
                'user_sub_activities.process_id',
                'processes.process_name',
                'user_sub_activities.productivity_status',
                'processes.type',
                'processes.icon',
                DB::raw('SUM(TIMESTAMPDIFF(SECOND, user_sub_activities.start_datetime, user_sub_activities.end_datetime)) AS total_seconds'),
            )
            ->join('user_activities', 'user_activities.id', '=', 'user_sub_activities.user_activity_id')
            ->where('user_activities.user_id', $userId)
            ->where('processes.process_name', '!=', '-1')
            ->where('processes.process_name', '!=', 'LockApp')
            ->where('processes.process_name', '!=', 'Idle')
            ->when($projectId != 0, function ($query) use ($projectId) {
                $query->where('user_activities.project_id', $projectId);
            })
            ->whereRaw("DATE(user_activities.start_datetime) BETWEEN ? AND ?", [$startDate, $endDate])
            ->whereRaw("DATE(user_activities.end_datetime) BETWEEN ? AND ?", [$startDate, $endDate])
            ->whereRaw("DATE(user_activities.start_datetime) = DATE(user_activities.end_datetime)")
            ->havingRaw('SUM(TIMESTAMPDIFF(SECOND, user_sub_activities.start_datetime, user_sub_activities.end_datetime)) >= 60')
            ->groupBy('user_sub_activities.process_id', 'processes.process_name', 'user_sub_activities.productivity_status', 'processes.type', 'processes.icon')
            ->orderByDesc('total_seconds')
            ->get();

        foreach ($userSubActivityData as $item) {
            $item->icon_url = asset('storage/' . ($item->icon ?? config(('app.web_default_image'))));
            $item->total_time = Helper::convertSecondsInReadableFormat($item->total_seconds);
        }

        return $userSubActivityData;
    }

    private function getProductiveNonProductiveTimeByEachDay($userId, $startDate, $endDate, $teamRecords = true)
    {
        $summaries = DB::table('user_productivity_summaries as ups')
            ->join('users', 'users.id', '=', 'ups.user_id')
            ->select([
                'ups.date',
                DB::raw('SUM(ups.productive_seconds) as total_productive_seconds'),
                DB::raw('SUM(ups.nonproductive_seconds) as total_nonproductive_seconds'),
                DB::raw('SUM(ups.neutral_seconds) as total_neutral_seconds')
            ])
            ->where(function ($query) use ($userId, $teamRecords) {
                if ($teamRecords) {
                    $query->where('users.parent_user_id', $userId)
                        ->orWhere('users.id', $userId);
                } else {
                    $query->where('users.id', $userId);
                }
            })
            ->whereBetween('ups.date', [$startDate, $endDate])
            ->groupBy('ups.date')
            ->orderBy('ups.date')
            ->get();

        return $summaries->map(function ($summary) {
            $date = Carbon::parse($summary->date)->format('d-m-Y');
            $productiveHours = round($summary->total_productive_seconds / 3600, 1);
            $nonproductiveHours = round($summary->total_nonproductive_seconds / 3600, 1);
            $neutralHours = round($summary->total_neutral_seconds / 3600, 1);

            return [
                'date' => $date,
                'productive' => $productiveHours,
                'productive_tooltip' => Helper::convertSecondsInReadableFormat($summary->total_productive_seconds),
                'nonproductive' => $nonproductiveHours,
                'nonproductive_tooltip' => Helper::convertSecondsInReadableFormat($summary->total_nonproductive_seconds),
                'neutral' => $neutralHours,
                'neutral_tooltip' => Helper::convertSecondsInReadableFormat($summary->total_neutral_seconds),
            ];
        })->toArray();
    }

    public static function getUserActivitySlots($userId, $startDate, $endDate)
    {
        $slots = [];
        $activities = UserActivity::where('user_activities.user_id', $userId)
            ->join('processes as p', 'user_activities.process_id', '=', 'p.id')
            ->whereBetween('user_activities.start_datetime', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->whereNotIn('p.process_name', ['-1', 'LockApp', 'Idle'])
            ->select(
                'user_activities.id',
                'user_activities.start_datetime',
                'user_activities.end_datetime',
                'user_activities.productivity_status',
                'p.process_name',
                'p.type',
                'p.icon',
                DB::raw('TIMESTAMPDIFF(SECOND, user_activities.start_datetime, user_activities.end_datetime) as duration')
            )
            ->having('duration', '>=', 5)
            ->orderBy('user_activities.start_datetime')
            ->get();

        $browserActivityIds = $activities->where('type', 'BROWSER')->pluck('id')->toArray();

        $subActivities = [];
        if (!empty($browserActivityIds)) {
            $subActivitiesCollection = UserSubActivity::whereIn('user_activity_id', $browserActivityIds)
                ->join('processes as sp', 'user_sub_activities.process_id', '=', 'sp.id')
                ->whereNotIn('sp.process_name', ['-1', 'LockApp', 'Idle'])
                ->select(
                    'user_sub_activities.user_activity_id',
                    'user_sub_activities.id',
                    'user_sub_activities.start_datetime',
                    'user_sub_activities.end_datetime',
                    'user_sub_activities.productivity_status',
                    'sp.process_name',
                    'sp.type',
                    'sp.icon',
                    DB::raw('TIMESTAMPDIFF(SECOND, user_sub_activities.start_datetime, user_sub_activities.end_datetime) as duration')
                )
                ->having('duration', '>=', 5)
                ->orderBy('user_sub_activities.start_datetime')
                ->get();

            $subActivities = $subActivitiesCollection->groupBy('user_activity_id');
        }

        $period = new \DatePeriod(
            new \DateTime($startDate),
            new \DateInterval('P1D'),
            (new \DateTime($endDate))->modify('+1 day')
        );

        foreach ($period as $date) {
            $dayStart = $date->format('Y-m-d 00:00:00');
            $dayEnd = $date->format('Y-m-d 23:59:59');

            $dayActivities = $activities->filter(function ($activity) use ($dayStart, $dayEnd) {
                return $activity->start_datetime >= $dayStart && $activity->start_datetime <= $dayEnd;
            });

            $daySlots = [];

            for ($hour = 0; $hour < 24; $hour += 2) {
                $slotStart = $date->format('Y-m-d') . ' ' . str_pad($hour, 2, '0', STR_PAD_LEFT) . ':00:00';
                $slotEnd = $date->format('Y-m-d') . ' ' . str_pad($hour + 2, 2, '0', STR_PAD_LEFT) . ':00:00';

                $slotActivities = $dayActivities->filter(function ($activity) use ($slotStart, $slotEnd) {
                    return $activity->start_datetime >= $slotStart && $activity->start_datetime < $slotEnd;
                });

                $processedActivities = collect();
                $productiveSeconds = 0;
                $nonProductiveSeconds = 0;
                $neutralSeconds = 0;

                foreach ($slotActivities as $activity) {
                    $activity->icon_url = asset('storage/' . ($activity->icon ?? config('app.process_default_image')));
                    $activity->time_used_human = Helper::convertSecondsInReadableFormat($activity->duration);

                    if ($activity->type === 'BROWSER') {
                        if (isset($subActivities[$activity->id]) && $subActivities[$activity->id]->isNotEmpty()) {
                            foreach ($subActivities[$activity->id] as $subActivity) {
                                $subActivity->icon_url = asset('storage/' . ($subActivity->icon ?? config('app.process_default_image')));
                                $subActivity->time_used_human = Helper::convertSecondsInReadableFormat($subActivity->duration);
                                $processedActivities->push($subActivity);

                                $duration = $subActivity->duration;
                                if ($subActivity->productivity_status === 'PRODUCTIVE') {
                                    $productiveSeconds += $duration;
                                } elseif ($subActivity->productivity_status === 'NONPRODUCTIVE') {
                                    $nonProductiveSeconds += $duration;
                                } else {
                                    $neutralSeconds += $duration;
                                }
                            }
                        }
                    } else {
                        $processedActivities->push($activity);
                        $duration = $activity->duration;
                        if ($activity->productivity_status === 'PRODUCTIVE') {
                            $productiveSeconds += $duration;
                        } elseif ($activity->productivity_status === 'NONPRODUCTIVE') {
                            $nonProductiveSeconds += $duration;
                        } else {
                            $neutralSeconds += $duration;
                        }
                    }
                }

                $totalSeconds = $productiveSeconds + $nonProductiveSeconds + $neutralSeconds;

                if ($totalSeconds > 0) {
                    if ($productiveSeconds >= ($totalSeconds / 2)) {
                        $slotStatus = 'PRODUCTIVE';
                    } elseif ($nonProductiveSeconds >= ($totalSeconds / 2)) {
                        $slotStatus = 'NONPRODUCTIVE';
                    } else {
                        $slotStatus = 'NEUTRAL';
                    }
                } else {
                    $slotStatus = 'NO_DATA';
                }

                $daySlots[] = [
                    'slot_start' => $slotStart,
                    'slot_end'   => $slotEnd,
                    'status'     => $slotStatus,
                    'activities' => $processedActivities->values()
                ];
            }

            $slots[$date->format('Y-m-d')] = $daySlots;
        }

        return $slots;
    }
}