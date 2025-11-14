<?php

namespace App\Http\Controllers\V2\App;

use App\Http\Controllers\Controller;
use App\Helpers\Helper;
use App\Models\Process;
use App\Models\UserActivity;
use App\Models\UserSubActivity;
use Carbon\Carbon;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class UserActivityController extends Controller
{
    public function createUserActivity(Request $request)
    {
        $neutralSeconds = 0;
        $productiveSeconds = 0;
        $nonproductiveSeconds = 0;
        if ($request->all()) {
            $userId = auth()->user()->id;
            $processedActivityData = $this->getActivityStartEndTime($request->all());

            foreach ($processedActivityData as $activity) {
                $activity['process'] = strtolower(trim(pathinfo($activity['process'], PATHINFO_FILENAME)));
                $process = Process::firstOrCreate([
                    'process_name' => $activity['process'],
                    'type' => $activity['type'],
                ]);

                $userActivity = null;
                if ($activity['endDateTime'] != '' && $activity['endDateTime'] != null) {
                    $userActivity = UserActivity::create([
                        'user_id' => $userId,
                        'process_id' => $process->id,
                        'productivity_status' => $activity['productivityStatus'],
                        'start_datetime' => $activity['startDateTime'],
                        'end_datetime' => $activity['endDateTime'],
                        'project_id' => $activity['projectId'] ?? null,
                        'task_id' => $activity['taskId'] ?? null,
                    ]);
                }

                // Check if there are sub-processes
                $isSubActivityEmpty = empty($activity['subProcess']);

                if ($isSubActivityEmpty) {
                    // No sub-processes, calculate duration for main activity
                    $start = Carbon::parse($activity['startDateTime']);
                    $end = Carbon::parse($activity['endDateTime']);

                    if ($activity['process'] != '-1' && $activity['process'] != 'lockapp' && $activity['process'] != 'idleapp') {
                        if ($start && $end) {
                            $durationInSeconds = $start->diffInSeconds($end);
                        } else {
                            $durationInSeconds = 0;
                        }
                    } else {
                        $durationInSeconds = 0;
                    }

                    switch (strtoupper($activity['productivityStatus'])) {
                        case 'NEUTRAL':
                            $neutralSeconds += $durationInSeconds;
                            break;
                        case 'PRODUCTIVE':
                            $productiveSeconds += $durationInSeconds;
                            break;
                        case 'NONPRODUCTIVE':
                            $nonproductiveSeconds += $durationInSeconds;
                            break;
                    }
                } else {
                    // Process sub-activities (only when there are sub-processes)
                    foreach ($activity['subProcess'] as $subProcess) {
                        if ($activity['type'] == 'BROWSER') {
                            if ($subProcess['url'] == '' || $subProcess['url'] == null) {
                                continue;
                            }

                            $websiteProcess = Process::firstOrCreate([
                                'process_name' => $subProcess['url'],
                                'type' => 'WEBSITE',
                            ]);

                            if (!$websiteProcess->icon) {
                                $faviconPath = $this->downloadFavicon($subProcess['url']);
                                $websiteProcess->icon = $faviconPath;
                                $websiteProcess->save();
                            }

                            if ($subProcess['endDateTime'] != '' && $subProcess['endDateTime'] != null && $userActivity) {
                                UserSubActivity::create([
                                    'user_activity_id' => $userActivity->id,
                                    'process_id' => $websiteProcess->id,
                                    'title' => $subProcess['title'] ?? '',
                                    'website_url' => $subProcess['url'],
                                    'productivity_status' => $subProcess['productivityStatus'],
                                    'start_datetime' => $subProcess['startDateTime'],
                                    'end_datetime' => $subProcess['endDateTime'],
                                    'project_id' => $subProcess['projectId'] ?? null,
                                    'task_id' => $subProcess['taskId'] ?? null,
                                ]);

                                $start = Carbon::parse($subProcess['startDateTime']);
                                $end = Carbon::parse($subProcess['endDateTime']);

                                if ($subProcess['url'] != '-1' && $subProcess['url'] != 'lockapp' && $subProcess['url'] != 'idleapp') {
                                    if ($start && $end) {
                                        $durationInSeconds = $start->diffInSeconds($end);
                                    } else {
                                        $durationInSeconds = 0;
                                    }
                                } else {
                                    $durationInSeconds = 0;
                                }

                                switch (strtoupper($subProcess['productivityStatus'])) {
                                    case 'NEUTRAL':
                                        $neutralSeconds += $durationInSeconds;
                                        break;
                                    case 'PRODUCTIVE':
                                        $productiveSeconds += $durationInSeconds;
                                        break;
                                    case 'NONPRODUCTIVE':
                                        $nonproductiveSeconds += $durationInSeconds;
                                        break;
                                }
                            }
                        }
                    }
                }
            }

            $this->updateProductivitySummary(
                $userId,
                now()->format('Y-m-d'),
                $productiveSeconds,
                $nonproductiveSeconds,
                $neutralSeconds,
                ($productiveSeconds + $nonproductiveSeconds + $neutralSeconds)
            );
        }

        $response = [
            'status_code' => 1,
            'data' => ['total_time' => $productiveSeconds + $nonproductiveSeconds + $neutralSeconds],
            'message' => 'Hurray !'
        ];

        return response()->json($response);
    }
    public function fetchUserTotalTimeInSeconds()
    {
        $userId = auth()->user()->id;

        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();
        $totalTimeInHours = Helper::calculateTotalHoursByUserId($userId, $startDate, $endDate, null, false);
        $totalTimeInSeconds = $totalTimeInHours * 3600;
        $response = [
            'status_code' => 1,
            'data' => ['total_time' => $totalTimeInSeconds]
        ];

        return response()->json($response);
    }
    private function getActivityStartEndTime($batchData): array
    {
        $processedData = [];
        $responseData = [];

        foreach ($batchData as $key => $data) {
            $processName = $data['ProcessName'];
            $processName = strtolower(trim(pathinfo($processName, PATHINFO_FILENAME)));
            $preTimestamp = $data['DateTime'];
            $dateTime = new DateTime($preTimestamp);
            $timestamp = $dateTime->format('Y-m-d H:i:s');
            $url = Helper::getDomainFromUrl($data['Url']);
            $processType = Helper::computeType($processName);

            if (!isset($processedData[$processName])) {
                // Initialize subprocess data for new process
                $subProcessData = [];
                if ($processType === 'BROWSER' && (!empty($url) || !empty($data['Title']))) {
                    $subProcessData = [
                        [
                            'url' => $url,
                            'startDateTime' => $timestamp,
                            'endDateTime' => $timestamp,
                            'title' => $data['Title'],
                            'productivityStatus' => Helper::computeActivityProductivityStatus(Helper::getDomainFromUrl($url), auth()->user()->id),
                            'projectId' => $data['ProjectId'] ?? null,
                            'taskId' => $data['TaskId'] ?? null,
                        ]
                    ];
                }

                $processedData[$processName] = [
                    'process' => $processName,
                    'startDateTime' => $timestamp,
                    'endDateTime' => $timestamp,
                    'type' => $processType,
                    'productivityStatus' => Helper::computeActivityProductivityStatus($processName, auth()->user()->id),
                    'subProcess' => $subProcessData,
                    'projectId' => $data['ProjectId'] ?? null,
                    'taskId' => $data['TaskId'] ?? null,
                ];
            } else {
                // Update end time for the current process
                $processedData[$processName]['endDateTime'] = $timestamp;

                // Work with the current subprocess data from processedData
                $currentSubProcessData = &$processedData[$processName]['subProcess'];

                $subProcessDataLen = count($currentSubProcessData) - 1;

                if ($subProcessDataLen >= 0) {
                    // Check if URL matches the last subprocess entry
                    if ($currentSubProcessData[$subProcessDataLen]['url'] == $url) {
                        // Update the end time for the current subprocess
                        $currentSubProcessData[$subProcessDataLen]['endDateTime'] = $timestamp;
                    } else {
                        // Different URL, create new subprocess entry
                        if ($processType === 'BROWSER' && (!empty($url) || !empty($data['Title']))) {
                            $newSubProcessData = [
                                'url' => $url,
                                'startDateTime' => $timestamp,
                                'endDateTime' => $timestamp,
                                'title' => $data['Title'],
                                'productivityStatus' => Helper::computeActivityProductivityStatus(Helper::getDomainFromUrl($url), auth()->user()->id),
                                'projectId' => $data['ProjectId'] ?? null,
                                'taskId' => $data['TaskId'] ?? null,
                            ];
                            $currentSubProcessData[] = $newSubProcessData;
                        }
                    }
                }
            }

            // If the next process is different or this is the last entry, store the details
            if (!isset($batchData[$key + 1]) || $batchData[$key + 1]['ProcessName'] !== $data['ProcessName']) {
                $responseData[] = [
                    'process' => $processName,
                    'startDateTime' => $processedData[$processName]['startDateTime'],
                    'endDateTime' => $processedData[$processName]['endDateTime'],
                    'type' => $processedData[$processName]['type'],
                    'productivityStatus' => $processedData[$processName]['productivityStatus'],
                    'subProcess' => $processedData[$processName]['subProcess'],
                    'projectId' => $processedData[$processName]['projectId'],
                    'taskId' => $processedData[$processName]['taskId'],
                ];
                unset($processedData[$processName]);
            }
        }

        return $responseData;
    }
    public function getTotalTimeWorked(Request $request)
    {
        $date = $request->date;
        $result = DB::table('user_activities as ua')
            ->select('ua.user_id', DB::raw('DATE(ua.start_datetime) AS work_date'))
            ->selectRaw('COALESCE(SUM(TIMESTAMPDIFF(SECOND, ua.start_datetime, ua.end_datetime)), 0) AS total_time_worked')
            ->whereDate('ua.start_datetime', $date)
            ->groupBy('ua.user_id', 'work_date')
            ->get();

        return response()->json($result);
    }
    private function downloadFavicon($url)
    {
        $domain = $url;
        $faviconUrl = "https://www.google.com/s2/favicons?domain={$domain}&sz=64";
        $fallbackIconPath = config('app.web_default_image');
        try {
            $faviconImage = Http::get($faviconUrl)->body();
            $defaultFaviconUrl = "https://www.google.com/s2/favicons?domain=example.com&sz=64";
            $defaultFaviconResponse = Http::get($defaultFaviconUrl);
            if ($faviconImage === $defaultFaviconResponse->body()) {
                return $fallbackIconPath;
            }

            $faviconPath = "uploads/favicons/{$domain}.png";
            Storage::disk('public')->put($faviconPath, $faviconImage);
            return $faviconPath;
        } catch (\Exception $e) {
            // Handle errors, e.g., if favicon fetching fails
            print("Failed to fetch favicon for {$domain}: " . $e->getMessage());
        }
    }
    private function updateProductivitySummary($userId, $date, $productiveMins, $nonproductiveMins, $neutralMins, $totalMins)
    {
        DB::table('user_productivity_summaries')
            ->updateOrInsert(
                ['user_id' => $userId, 'date' => $date],
                [
                    'productive_seconds' => DB::raw("productive_seconds + $productiveMins"),
                    'nonproductive_seconds' => DB::raw("nonproductive_seconds + $nonproductiveMins"),
                    'neutral_seconds' => DB::raw("neutral_seconds + $neutralMins"),
                    'total_seconds' => DB::raw("total_seconds + $totalMins"),
                ]
            );
    }
}
