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
            'timezone_offset_minutes' => 'nullable|integer'
        ]);
        $processReqOnly = $request->input('process_req_only', false);
        $subActivityReqOnly = $request->input('subactivity_req_only', false);
        $projectId = $request->input('project_id', 0);

        if($request->input('user_id') == 0) {
            return response()->json(config('dummy.report'));
        }

        $timezoneOffsetMinutes = $request->input('timezone_offset_minutes', 0);
        $hours = floor(abs($timezoneOffsetMinutes) / 60);
        $minutes = abs($timezoneOffsetMinutes) % 60;
        $sign = $timezoneOffsetMinutes >= 0 ? '+' : '-';
        $tz = sprintf('%s%02d:%02d', $sign, $hours, $minutes);

        $startDateLocal = Carbon::parse(
            $request->input('start_date', Carbon::now()->setTimezone($tz)->subDays(7)->toDateString()),
            $tz
        )->startOfDay();

        $endDateLocal = Carbon::parse(
            $request->input('end_date', Carbon::now()->setTimezone($tz)->toDateString()),
            $tz
        )->endOfDay();

        $startUTC = $startDateLocal->copy()->setTimezone('UTC');
        $endUTC = $endDateLocal->copy()->setTimezone('UTC');

        $userId = $request->input('user_id');

        if ($processReqOnly) {
            $process = $this->getProcessDataWithScreenshots($userId, $startUTC, $endUTC, $projectId);
            return response()->json(['dummy'=> false, 'status_code' => 1, 'data' => ['process' => $process]]);
        }

        if ($subActivityReqOnly) {
            $userSubActivities = $this->getSubActivityDataWithScreenshots($userId, $startUTC, $endUTC, $projectId);
            return response()->json(['dummy'=> false, 'status_code' => 1, 'data' => ['user_sub_activities' => $userSubActivities]]);
        }
        // Existing data
        $process = $this->getProcessDataWithScreenshots($userId, $startUTC, $endUTC, $projectId);
        $totalProductiveHours = Helper::calculateTotalHoursByUserId($userId, $startDateLocal, $endDateLocal, 'PRODUCTIVE');
        $userWorkingTrend = $this->getProductiveNonProductiveTimeByEachDay($userId, $startDateLocal, $endDateLocal, false);
        $userAttendance = Helper::getUserAttendance($userId, $startDateLocal->copy(), $endDateLocal->copy());
        $totalHoursWorked = Helper::calculateTotalHoursByUserId($userId, $startDateLocal, $endDateLocal);
        $userSubActivities = $this->getSubActivityDataWithScreenshots($userId, $startUTC, $endUTC, $projectId);
        $userTimeline = $this->getUserActivitySlots($userId, $startDateLocal->toDateString(), $endDateLocal->toDateString(), $timezoneOffsetMinutes);

        // New data - Projects and Tasks - NOW USING HELPER
        $totalProjectsAssigned = Helper::getTotalProjectsAssigned($userId);
        $totalTasksAssigned = Helper::getTotalTasksAssigned($userId);
        $tasksOverdue = Helper::getTasksOverdue($userId,false);
        $projectsAssignedList = Helper::getProjectsForUser($userId);

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

    private function getProcessDataWithScreenshots($userId, $startUTC, $endUTC, $projectId = 0)
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
            ->whereBetween('user_activities.start_datetime', [$startUTC, $endUTC])
            ->whereBetween('user_activities.end_datetime', [$startUTC, $endUTC])
            ->havingRaw('SUM(TIMESTAMPDIFF(SECOND, user_activities.start_datetime, user_activities.end_datetime)) >= 60')
            ->groupBy('user_activities.process_id', 'processes.process_name', 'user_activities.productivity_status', 'processes.type', 'processes.icon')
            ->orderByDesc('total_seconds')
            ->get();

        foreach ($processData as $item) {
            $processId = $item->process_id;

            $screenshots = UserScreenshot::whereBetween('created_at', [$startUTC, $endUTC])
                ->where('process_id', $processId)
                ->where('user_id', $userId)
                ->get();

            $item->icon_url = asset('storage/' . ($item->icon ?? config(('app.process_default_image'))));
            $item->total_time = Helper::convertSecondsInReadableFormat($item->total_seconds);
            $item->screenshots = $screenshots;
        }

        return $processData;
    }

    private function getSubActivityDataWithScreenshots($userId, $startUTC, $endUTC, $projectId = 0)
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
            ->whereBetween('user_activities.start_datetime', [$startUTC, $endUTC])
            ->whereBetween('user_activities.end_datetime', [$startUTC, $endUTC])
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
public static function getUserActivitySlots($userId, $startDate, $endDate, $timezoneOffsetMinutes = 0)
{
    $slots = [];

    // Create timezone string from offset (e.g. +05:30)
    $hours = floor(abs($timezoneOffsetMinutes) / 60);
    $minutes = abs($timezoneOffsetMinutes) % 60;
    $sign = $timezoneOffsetMinutes >= 0 ? '+' : '-';
    $tz = sprintf('%s%02d:%02d', $sign, $hours, $minutes);

    // 🔥 Convert user date range → UTC for DB query using proper timezone alignment
    $startUTC = Carbon::parse($startDate, $tz)->startOfDay()->setTimezone('UTC');
    $endUTC   = Carbon::parse($endDate, $tz)->endOfDay()->setTimezone('UTC');

    // 🔥 Fetch activities
    $activities = UserActivity::where('user_activities.user_id', $userId)
        ->join('processes as p', 'user_activities.process_id', '=', 'p.id')
        ->whereBetween('user_activities.start_datetime', [$startUTC, $endUTC])
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
        ->get()
        ->map(function ($a) use ($tz) {
            $a->local_start = Carbon::parse($a->start_datetime)->setTimezone($tz);
            $a->local_end   = Carbon::parse($a->end_datetime)->setTimezone($tz);
            return $a;
        })
        ->values();

    // 🔥 Browser activity IDs
    $browserActivityIds = $activities->where('type', 'BROWSER')->pluck('id');

    // 🔥 Fetch sub activities
    $subActivities = [];
    if ($browserActivityIds->isNotEmpty()) {
        $subActivities = UserSubActivity::whereIn('user_activity_id', $browserActivityIds)
            ->join('processes as sp', 'user_sub_activities.process_id', '=', 'sp.id')
            ->whereBetween('user_sub_activities.start_datetime', [$startUTC, $endUTC])
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
            ->get()
            ->map(function ($a) use ($tz) {
                $a->local_start = Carbon::parse($a->start_datetime)->setTimezone($tz);
                $a->local_end   = Carbon::parse($a->end_datetime)->setTimezone($tz);
                return $a;
            })
            ->groupBy('user_activity_id');
    }

    // 🔥 Iterate days in USER LOCAL TIME
    $period = Carbon::parse($startDate, $tz)->toPeriod(Carbon::parse($endDate, $tz));

    foreach ($period as $date) {
        $date->setTimezone($tz);
        $dayStart = $date->copy()->startOfDay();
        $dayEnd   = $date->copy()->endOfDay();

        $dayActivities = $activities->filter(fn($a) =>
            $a->local_end > $dayStart && $a->local_start < $dayEnd
        );

        $daySlots = [];

        // 🔥 2-hour slots aligned to LOCAL time
        for ($hour = 0; $hour < 24; $hour += 2) {
            $slotStart = $date->copy()->setTime($hour, 0, 0);
            $slotEnd   = $slotStart->copy()->addHours(2)->subSecond();

            $processed = collect();
            $productive = $nonProductive = $neutral = 0;

            $slotStartBoundary = $slotStart->timestamp;
            $slotEndBoundary = $slotStart->copy()->addHours(2)->timestamp;

            foreach ($dayActivities as $activity) {

                $overlapStart = max($activity->local_start->timestamp, $slotStartBoundary);
                $overlapEnd = min($activity->local_end->timestamp, $slotEndBoundary);

                if ($overlapStart >= $overlapEnd) {
                    continue;
                }

                $overlapDuration = $overlapEnd - $overlapStart;
                $iconUrl = asset('storage/' . ($activity->icon ?? config('app.process_default_image')));
                $timeHuman = Helper::convertSecondsInReadableFormat($overlapDuration);

                if ($activity->type === 'BROWSER' && isset($subActivities[$activity->id])) {

                    $hasSubInSlot = false;

                    foreach ($subActivities[$activity->id] as $sub) {
                        $subOverlapStart = max($sub->local_start->timestamp, $slotStartBoundary);
                        $subOverlapEnd = min($sub->local_end->timestamp, $slotEndBoundary);

                        if ($subOverlapStart >= $subOverlapEnd) {
                            continue;
                        }

                        $hasSubInSlot = true;
                        $subOverlapDuration = $subOverlapEnd - $subOverlapStart;

                        $subIcon = asset('storage/' . ($sub->icon ?? config('app.process_default_image')));
                        $subTime = Helper::convertSecondsInReadableFormat($subOverlapDuration);

                        $processed->push((object)[
                            'id' => $sub->id,
                            'process_name' => $sub->process_name,
                            'type' => $sub->type,
                            'productivity_status' => $sub->productivity_status,
                            'duration' => $subOverlapDuration,
                            'icon_url' => $subIcon,
                            'time_used_human' => $subTime,
                            'local_start' => Carbon::createFromTimestamp($subOverlapStart)->setTimezone($tz),
                            'local_end' => Carbon::createFromTimestamp($subOverlapEnd)->setTimezone($tz),
                        ]);

                        match ($sub->productivity_status) {
                            'PRODUCTIVE' => $productive += $subOverlapDuration,
                            'NONPRODUCTIVE' => $nonProductive += $subOverlapDuration,
                            default => $neutral += $subOverlapDuration,
                        };
                    }

                    // ✅ fallback if no sub activity
                    if (!$hasSubInSlot) {
                        $processed->push((object)[
                            'id' => $activity->id,
                            'process_name' => $activity->process_name,
                            'type' => $activity->type,
                            'productivity_status' => $activity->productivity_status,
                            'duration' => $overlapDuration,
                            'icon_url' => $iconUrl,
                            'time_used_human' => $timeHuman,
                            'local_start' => Carbon::createFromTimestamp($overlapStart)->setTimezone($tz),
                            'local_end' => Carbon::createFromTimestamp($overlapEnd)->setTimezone($tz),
                        ]);

                        match ($activity->productivity_status) {
                            'PRODUCTIVE' => $productive += $overlapDuration,
                            'NONPRODUCTIVE' => $nonProductive += $overlapDuration,
                            default => $neutral += $overlapDuration,
                        };
                    }

                } else {
                    $processed->push((object)[
                        'id' => $activity->id,
                        'process_name' => $activity->process_name,
                        'type' => $activity->type,
                        'productivity_status' => $activity->productivity_status,
                        'duration' => $overlapDuration,
                        'icon_url' => $iconUrl,
                        'time_used_human' => $timeHuman,
                        'local_start' => Carbon::createFromTimestamp($overlapStart)->setTimezone($tz),
                        'local_end' => Carbon::createFromTimestamp($overlapEnd)->setTimezone($tz),
                    ]);

                    match ($activity->productivity_status) {
                        'PRODUCTIVE' => $productive += $overlapDuration,
                        'NONPRODUCTIVE' => $nonProductive += $overlapDuration,
                        default => $neutral += $overlapDuration,
                    };
                }
            }

            $total = $productive + $nonProductive + $neutral;

            $status = match (true) {
                $total === 0 => 'NO_DATA',
                $productive >= $total / 2 => 'PRODUCTIVE',
                $nonProductive >= $total / 2 => 'NONPRODUCTIVE',
                default => 'NEUTRAL',
            };

            // 🔥 Sort + merge
            $merged = $processed
                ->sortBy(fn($a) => $a->local_start->timestamp)
                ->values()
                ->reduce(function ($carry, $act) {

                    $last = $carry->last();

                    if ($last &&
                        $last->process_name === $act->process_name &&
                        $last->productivity_status === $act->productivity_status
                    ) {
                        $last->duration += $act->duration;
                        $last->local_end = $act->local_end;
                        $last->time_used_human = Helper::convertSecondsInReadableFormat($last->duration);
                        return $carry;
                    }

                    return $carry->push(clone $act);

                }, collect());

            // 🔥 Final output (ISO format with timezone)
            $merged = $merged->map(function ($a) {
                return [
                    'id' => $a->id,
                    'process_name' => $a->process_name,
                    'type' => $a->type,
                    'productivity_status' => $a->productivity_status,
                    'duration' => $a->duration,
                    'icon_url' => $a->icon_url,
                    'time_used_human' => $a->time_used_human,
                    'start_datetime' => $a->local_start->toIso8601String(),
                    'end_datetime'   => $a->local_end->toIso8601String(),
                ];
            });

            $daySlots[] = [
                'slot_start' => $slotStart->toIso8601String(),
                'slot_end'   => $slotEnd->toIso8601String(),
                'status'     => $status,
                'activities' => $merged->values()
            ];
        }

        $slots[$date->format('Y-m-d')] = $daySlots;
    }

    return $slots;
}
}
