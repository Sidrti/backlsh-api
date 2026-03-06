<?php

namespace App\Http\Controllers\V1\App;

use App\Http\Controllers\Controller;
use App\Helpers\Helper;
use App\Models\Process;
use App\Models\Project;
use App\Models\Task;
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
    // public function createUserActivity(Request $request)
    // {
    //     $inputData = $request->all();
    //     if (empty($inputData)) {
    //         return response()->json(['status_code' => 0, 'message' => 'No data provided']);
    //     }

    //     $neutralSeconds = 0;
    //     $productiveSeconds = 0;
    //     $nonproductiveSeconds = 0;
        
    //     $userId = auth()->user()->id;
    //     $processedActivityData = $this->getActivityStartEndTime($inputData);
    //     $userProjectRules = $this->getUserProjectRules($userId);

    //     // FIX: Normalize exclusion list to lowercase to match normalized input
    //     $excludedApps = ['-1', 'lockapp', 'idleapp', 'idle'];

    //     foreach ($processedActivityData as $activity) {
    //         // FIX: Consistently normalize process name immediately
    //         $processRawName = strtolower(trim(pathinfo($activity['process'], PATHINFO_FILENAME)));
            
    //         $process = Process::firstOrCreate([
    //             'process_name' => $processRawName,
    //             'type' => $activity['type'],
    //         ]);

    //         $projectId = $this->determineProjectId($processRawName, $activity['projectId'] ?? null, $userProjectRules);
    //         $taskId = $activity['taskId'] ?? null;

    //         // FIX: Performance - Only query DB if an ID actually exists
    //         if ($projectId !== null && !Project::where('id', $projectId)->exists()) {
    //             $projectId = null;
    //         }
    //         if ($taskId !== null && !Task::where('id', $taskId)->exists()) {
    //             $taskId = null;
    //         }

    //         // FIX: THE TIME LEAK FIX
    //         // Always calculate duration based on the Application (Main Process) start/end
    //         $start = Carbon::parse($activity['startDateTime']);
    //         $end = Carbon::parse($activity['endDateTime']);
    //         $durationInSeconds = ($start && $end) ? $start->diffInSeconds($end) : 0;

    //         // FIX: Case-insensitive check for Idle/LockApp
    //         if (!in_array($processRawName, $excludedApps)) {
    //             switch (strtoupper($activity['productivityStatus'])) {
    //                 case 'NEUTRAL':
    //                     $neutralSeconds += $durationInSeconds;
    //                     break;
    //                 case 'PRODUCTIVE':
    //                     $productiveSeconds += $durationInSeconds;
    //                     break;
    //                 case 'NONPRODUCTIVE':
    //                     $nonproductiveSeconds += $durationInSeconds;
    //                     break;
    //             }
    //         }

    //         // Create the Main Activity record
    //         $userActivity = null;
    //         if ($activity['endDateTime'] != '' && $activity['endDateTime'] != null) {
    //             $userActivity = UserActivity::create([
    //                 'user_id' => $userId,
    //                 'process_id' => $process->id,
    //                 'project_id' => $projectId,
    //                 'task_id' => $taskId,
    //                 'productivity_status' => $activity['productivityStatus'],
    //                 'start_datetime' => $activity['startDateTime'],
    //                 'end_datetime' => $activity['endDateTime'],
    //             ]);
    //         }

    //         // FIX: Handle Sub-Processes for LOGGING only
    //         // Duration math is already handled above, so gaps between tabs won't lose time anymore
    //         if (!empty($activity['subProcess']) && $userActivity) {
    //             foreach ($activity['subProcess'] as $subProcess) {
    //                 // Ignore truly empty browser pings but don't skip the main time
    //                 if (empty($subProcess['url'])) {
    //                     continue;
    //                 }

    //                 $websiteProcess = Process::firstOrCreate([
    //                     'process_name' => $subProcess['url'],
    //                     'type' => 'WEBSITE',
    //                 ]);

    //                 if (!$websiteProcess->icon) {
    //                     $websiteProcess->icon = $this->downloadFavicon($subProcess['url']);
    //                     $websiteProcess->save();
    //                 }

    //                 $subProjectId = $this->determineProjectId(
    //                     $subProcess['url'],
    //                     $subProcess['projectId'] ?? null,
    //                     $userProjectRules,
    //                     'WEBSITE'
    //                 );

    //                 UserSubActivity::create([
    //                     'user_activity_id' => $userActivity->id,
    //                     'process_id' => $websiteProcess->id,
    //                     'project_id' => $subProjectId,
    //                     'task_id' => $subProcess['taskId'] ?? null,
    //                     'title' => $subProcess['title'] ?? '',
    //                     'website_url' => $subProcess['url'],
    //                     'productivity_status' => $subProcess['productivityStatus'],
    //                     'start_datetime' => $subProcess['startDateTime'],
    //                     'end_datetime' => $subProcess['endDateTime'],
    //                 ]);
    //             }
    //         }
    //     }

    //     // Update summary table with the final calculated batch totals
    //     $this->updateProductivitySummary(
    //         $userId,
    //         now()->format('Y-m-d'),
    //         $productiveSeconds,
    //         $nonproductiveSeconds,
    //         $neutralSeconds,
    //         ($productiveSeconds + $nonproductiveSeconds + $neutralSeconds)
    //     );

    //     return response()->json([
    //         'status_code' => 1,
    //         'data' => ['total_time' => $productiveSeconds + $nonproductiveSeconds + $neutralSeconds],
    //         'message' => 'Activity processed. Summary synchronized.'
    //     ]);
    // }
    public function createUserActivity(Request $request)
    {
        $inputData = $request->all();
        if (empty($inputData)) {
            return response()->json(['status_code' => 0, 'message' => 'No data provided']);
        }

        $userId = auth()->user()->id;

        // Wrap in a transaction to ensure if any part fails, no partial data is saved
        return DB::transaction(function () use ($inputData, $userId) {
            $processedActivityData = $this->getActivityStartEndTime($inputData);
            $userProjectRules = $this->getUserProjectRules($userId);

            // Normalized exclusion list
            $excludedApps = ['-1', 'lockapp', 'idleapp', 'idle'];

            foreach ($processedActivityData as $activity) {
                // 1. Normalize and find/create the Main Process
                $processRawName = strtolower(trim(pathinfo($activity['process'], PATHINFO_FILENAME)));
                
                $process = Process::firstOrCreate([
                    'process_name' => $processRawName,
                    'type' => $activity['type'],
                ]);

                // 2. Determine Project/Task
                $projectId = $this->determineProjectId($processRawName, $activity['projectId'] ?? null, $userProjectRules);
                if (empty($projectId) || $projectId == 0) {
                    $projectId = null;
                }
                $taskId = $activity['taskId'] ?? null;
                if (empty($taskId) || $taskId == 0) {
                    $taskId = null;
                }

                // Validate Project/Task exist if IDs were passed
                if ($projectId && !Project::where('id', $projectId)->exists()) $projectId = null;
                if ($taskId && !Task::where('id', $taskId)->exists()) $taskId = null;

                // 3. CREATE THE MAIN ACTIVITY RECORD
                // This is our "Source of Truth" for time.
                $userActivity = null;
                if (!empty($activity['endDateTime'])) {
                    $userActivity = UserActivity::create([
                        'user_id' => $userId,
                        'process_id' => $process->id,
                        'project_id' => $projectId,
                        'task_id' => $taskId,
                        'productivity_status' => strtoupper($activity['productivityStatus']),
                        'start_datetime' => $activity['startDateTime'],
                        'end_datetime' => $activity['endDateTime'],
                    ]);
                }

                // 4. LOG SUB-PROCESSES (Browsers)
                // We do NOT use these for productivity math to avoid "empty URL" time leaks.
                if ($userActivity && !empty($activity['subProcess'])) {
                    foreach ($activity['subProcess'] as $sub) {
                        if (empty($sub['url'])) continue;

                        $webProcess = Process::firstOrCreate([
                            'process_name' => $sub['url'],
                            'type' => 'WEBSITE',
                        ]);

                        if (!$webProcess->icon) {
                            $webProcess->icon = $this->downloadFavicon($sub['url']);
                            $webProcess->save();
                        }

                        $subProjectId = $this->determineProjectId($sub['url'], $sub['projectId'] ?? null, $userProjectRules, 'WEBSITE');
                        if (empty($subProjectId) || $subProjectId == 0) {
                            $subProjectId = null;
                        }

                        $subTaskId = $sub['taskId'] ?? null;
                        if (empty($subTaskId) || $subTaskId == 0) {
                            $subTaskId = null;
                        }

                        if ($subProjectId && !Project::where('id', $subProjectId)->exists()) {
                            $subProjectId = null;
                        }
                        if ($subTaskId && !Task::where('id', $subTaskId)->exists()) {
                            $subTaskId = null;
                        }

                        UserSubActivity::create([
                            'user_activity_id' => $userActivity->id,
                            'process_id' => $webProcess->id,
                            'project_id' => $subProjectId,
                            'task_id' => $subTaskId,
                            'title' => $sub['title'] ?? '',
                            'website_url' => $sub['url'],
                            'productivity_status' => strtoupper($sub['productivityStatus']),
                            'start_datetime' => $sub['startDateTime'],
                            'end_datetime' => $sub['endDateTime'],
                        ]);
                    }
                }
            }

            // 5. FINAL SYNC: Calculate totals from the database logs
            // This ensures user_productivity_summaries ALWAYS matches user_activities
            $today = now()->format('Y-m-d');
            $this->syncProductivitySummary($userId, $today, $excludedApps);

            return response()->json([
                'status_code' => 1,
                'message' => 'Logs processed and summary synchronized.'
            ]);
        });
    }

    /**
     * Fixes the 5h vs 7h discrepancy by calculating totals directly from 
     * the raw UserActivity logs instead of manual PHP increments.
     */
    private function syncProductivitySummary($userId, $date, $excludedApps)
    {
        $nonBrowserTotals = DB::table('user_activities')
            ->join('processes', 'processes.id', '=', 'user_activities.process_id')
            ->where('user_id', $userId)
            ->whereDate('start_datetime', $date)
            ->whereNotIn('processes.process_name', $excludedApps)
            ->where('processes.type', '!=', 'BROWSER')
            ->selectRaw("
                SUM(CASE WHEN productivity_status = 'PRODUCTIVE' THEN TIMESTAMPDIFF(SECOND, start_datetime, end_datetime) ELSE 0 END) as productive,
                SUM(CASE WHEN productivity_status = 'NONPRODUCTIVE' THEN TIMESTAMPDIFF(SECOND, start_datetime, end_datetime) ELSE 0 END) as nonproductive,
                SUM(CASE WHEN productivity_status = 'NEUTRAL' THEN TIMESTAMPDIFF(SECOND, start_datetime, end_datetime) ELSE 0 END) as neutral
            ")
            ->first();

        $browserTotalSeconds = DB::table('user_activities')
            ->join('processes', 'processes.id', '=', 'user_activities.process_id')
            ->where('user_activities.user_id', $userId)
            ->whereDate('user_activities.start_datetime', $date)
            ->whereNotIn('processes.process_name', $excludedApps)
            ->where('processes.type', 'BROWSER')
            ->selectRaw("COALESCE(SUM(TIMESTAMPDIFF(SECOND, user_activities.start_datetime, user_activities.end_datetime)), 0) as total")
            ->value('total');

        $browserBreakdown = DB::table('user_sub_activities as usa')
            ->join('user_activities as ua', 'ua.id', '=', 'usa.user_activity_id')
            ->join('processes as p', 'p.id', '=', 'ua.process_id')
            ->where('ua.user_id', $userId)
            ->whereDate('ua.start_datetime', $date)
            ->whereNotIn('p.process_name', $excludedApps)
            ->where('p.type', 'BROWSER')
            ->selectRaw("
                SUM(CASE WHEN usa.productivity_status = 'PRODUCTIVE' THEN TIMESTAMPDIFF(SECOND, usa.start_datetime, usa.end_datetime) ELSE 0 END) as productive,
                SUM(CASE WHEN usa.productivity_status = 'NONPRODUCTIVE' THEN TIMESTAMPDIFF(SECOND, usa.start_datetime, usa.end_datetime) ELSE 0 END) as nonproductive,
                SUM(CASE WHEN usa.productivity_status = 'NEUTRAL' THEN TIMESTAMPDIFF(SECOND, usa.start_datetime, usa.end_datetime) ELSE 0 END) as neutral
            ")
            ->first();

        $browserProductive = (int) ($browserBreakdown->productive ?? 0);
        $browserNonproductive = (int) ($browserBreakdown->nonproductive ?? 0);
        $browserNeutral = (int) ($browserBreakdown->neutral ?? 0);

        $browserClassified = $browserProductive + $browserNonproductive + $browserNeutral;
        $browserRemainder = (int) $browserTotalSeconds - $browserClassified;
        if ($browserRemainder > 0) {
            $browserNeutral += $browserRemainder;
        }

        $productiveSeconds = (int) ($nonBrowserTotals->productive ?? 0) + $browserProductive;
        $nonproductiveSeconds = (int) ($nonBrowserTotals->nonproductive ?? 0) + $browserNonproductive;
        $neutralSeconds = (int) ($nonBrowserTotals->neutral ?? 0) + $browserNeutral;

        DB::table('user_productivity_summaries')->updateOrInsert(
            ['user_id' => $userId, 'date' => $date],
            [
                'productive_seconds' => $productiveSeconds,
                'nonproductive_seconds' => $nonproductiveSeconds,
                'neutral_seconds' => $neutralSeconds,
                'total_seconds' => ($productiveSeconds + $nonproductiveSeconds + $neutralSeconds)
            ]
        );
    }
        public function fetchUserTotalTimeInSeconds()
        {
            $userId = auth()->user()->id;

            $startDate = Carbon::now()->startOfDay();
            $endDate = Carbon::now()->endOfDay();
            $totalTimeInHours = Helper::calculateTotalHoursByUserId($userId, $startDate, $endDate, null, false);
            $totalTimeInSeconds = $totalTimeInHours;
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
                        'projectId' => $data['ProjectId'] ?? null,
                        'taskId' => $data['TaskId'] ?? null,
                        'subProcess' => $subProcessData
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
                        'projectId' => $processedData[$processName]['projectId'],
                        'taskId' => $processedData[$processName]['taskId'],
                        'subProcess' => $processedData[$processName]['subProcess']
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
                    // Return fallback icon if the favicon is default
                    return $fallbackIconPath;
                }


                $faviconPath = "uploads/favicons/{$domain}.png";

                // $path = Helper::saveImageToServer($faviconImage,$dir);
                // return $path;
                Storage::disk('public')->put($faviconPath, $faviconImage);
                return $faviconPath;

                // // Update the icon field in the database
                // $websiteProcess->icon = $faviconPath;
                // $websiteProcess->save();
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
        /**
     * Fetch all active project rules for projects the user is a member of
     */
    private function getUserProjectRules($userId)
    {
        return DB::table('project_process_rules')
            ->join('project_members', 'project_process_rules.project_id', '=', 'project_members.project_id')
            ->where('project_members.user_id', $userId)
            ->where('project_process_rules.is_active', true)
            ->orderBy('project_process_rules.priority', 'desc')
            ->select('project_process_rules.*')
            ->get();
    }

/**
 * Determine the project_id based on process rules
 * 
 * @param string $processName The process or URL to match
 * @param int|null $userSelectedProjectId The project_id selected by user
 * @param Collection $rules Collection of project rules
 * @param string $type Type of process (default from activity type, or 'WEBSITE' for sub-activities)
 * @return int|null
 */
private function determineProjectId($processName, $userSelectedProjectId, $rules, $type = null)
{
    // First, check for priority rules that match
    $priorityRule = $this->findMatchingRule($processName, $rules->where('priority', true));
    if ($priorityRule) {
        // Priority rule found - always use this project_id
        return $priorityRule->project_id;
    }

    // No priority rule found, check for non-priority rules
    $nonPriorityRule = $this->findMatchingRule($processName, $rules->where('priority', false));
    
    if ($nonPriorityRule) {
        // Non-priority rule found, but user selection takes precedence
        if ($userSelectedProjectId !== null) {
            return $userSelectedProjectId;
        }
        return $nonPriorityRule->project_id;
    }

    // No rules matched, return user selected project_id
    return $userSelectedProjectId;
}

/**
 * Find a matching rule based on match_type
 * 
 * @param string $processName
 * @param Collection $rules
 * @return object|null
 */
private function findMatchingRule($processName, $rules)
{
    foreach ($rules as $rule) {
        $matched = false;
        
        switch ($rule->match_type) {
            case 'exact':
                $matched = strtolower($processName) === strtolower($rule->process);
                break;
                
            case 'contains':
                $matched = stripos($processName, $rule->process) !== false;
                break;
                
            case 'domain':
                // Extract domain from URL for domain matching
                $processDomain = Helper::getDomainFromUrl($processName);
                $ruleDomain = Helper::getDomainFromUrl($rule->process);
                $matched = $processDomain === $ruleDomain;
                break;
        }
        
        if ($matched) {
            return $rule;
        }
    }
    
    return null;
}

/**
 * Extract domain from URL
 * 
 * @param string $url
 * @return string
 */
// private function extractDomain($url)
// {
//     // Add scheme if missing
//     if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
//         $url = "http://" . $url;
//     }
    
//     $host = parse_url($url, PHP_URL_HOST);
    
//     if (!$host) {
//         return strtolower($url);
//     }
    
//     // Remove www. prefix
//     $domain = preg_replace('/^www\./', '', $host);
    
//     return strtolower($domain);
// }
}
