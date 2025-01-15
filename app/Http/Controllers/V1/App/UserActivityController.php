<?php

namespace App\Http\Controllers\V1\App;

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
        if($request->all())
        {
            $userId = auth()->user()->id;
            $processedActivityData = $this->getActivityStartEndTime($request->all());
            foreach ($processedActivityData as $activity) {
                $activity['process'] = strtolower(trim(pathinfo($activity['process'], PATHINFO_FILENAME)));
                $process = Process::firstOrCreate([
                    'process_name' => $activity['process'], 
                    'type' => $activity['type'], 
                ]);

                if($activity['endDateTime'] != '' && $activity['endDateTime'] != null) {
                    $userActivity = UserActivity::create([
                        'user_id' => $userId,
                        'process_id' => $process->id,
                        'productivity_status' => $activity['productivityStatus'],
                        'start_datetime' => $activity['startDateTime'],
                        'end_datetime' => $activity['endDateTime'],
                    ]);
                }
                // Insert sub-processes
               
                foreach ($activity['subProcess'] as $subProcess) {
                    $websiteProcess = null;
                    if($activity['type'] == 'BROWSER') {
                        if($subProcess['url'] == '' || $subProcess['url'] == null) {
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
                      
                        if($subProcess['endDateTime'] != '' && $subProcess['endDateTime'] != null && $subProcess['title'] != null) { 
                            UserSubActivity::create([
                                'user_activity_id' => $userActivity->id,
                                'process_id' => $websiteProcess != null && isset($websiteProcess->id) ? $websiteProcess->id : null,
                                'title' => $subProcess['title'],
                                'website_url' => $subProcess['url'],
                                'productivity_status' => $subProcess['productivityStatus'],
                                'start_datetime' => $subProcess['startDateTime'],
                                'end_datetime' => $subProcess['endDateTime'],
                            ]);
                        }  
                    }
                }
            }
        }

        $response = [
            'status_code' => 1,
            'data' => [],
            'message' => 'Hurray !' 
        ];

        return response()->json($response);
    }
    public function fetchUserTotalTimeInSeconds()
    {
        $userId = auth()->user()->id;
        
        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();
        $totalTimeInHours = Helper::calculateTotalHoursByUserId($userId,$startDate,$endDate,null,false);
        $totalTimeInSeconds = $totalTimeInHours * 3600;
        $response = [
            'status_code' => 1,
            'data' => ['total_time' => $totalTimeInSeconds ]
        ];

        return response()->json($response);

    }
    private function getActivityStartEndTime($batchData): array
    {
        $processedData = [];
        $subProcessData = [];
        $responseData = [];
      
        foreach ($batchData as $key => $data) {

            $processName = $data['ProcessName'];
            $processName = strtolower(trim(pathinfo($processName, PATHINFO_FILENAME)));
            $preTimestamp = $data['DateTime'];
            $dateTime = new DateTime($preTimestamp);
            $timestamp = $dateTime->format('Y-m-d H:i:s');
            $url =  Helper::getDomainFromUrl($data['Url']);
            $processType = Helper::computeType($processName);
        
            if (!isset($processedData[$processName])) {
                $subProcessData = [];
                if($processType === 'BROWSER') {
                    $subProcessData = [
                        [
                            'url' => $url,
                            'startDateTime' => $timestamp,
                            'endDateTime' => $timestamp,
                            'title' => $data['Title'],
                            'productivityStatus' => Helper::computeActivityProductivityStatus(Helper::getDomainFromUrl($url),auth()->user()->id),
                        ]
                    ];
                }
                $processedData[$processName] = [
                    'process' => $processName,
                    'startDateTime' => $timestamp,
                    'endDateTime' => $timestamp,
                    'type' => $processType,
                    'productivityStatus' => Helper::computeActivityProductivityStatus($processName,auth()->user()->id),
                    'subProcess' => $subProcessData
                ];
            } 
            else {
                // Update end time for the current process
                $processedData[$processName]['endDateTime'] = $timestamp;

                $subProcessDataLen = count($subProcessData) - 1;
                if($subProcessDataLen != -1)
                {
                    if($subProcessData[$subProcessDataLen ]['url'] == $url) {
                        $subProcessData[$subProcessDataLen]['endDateTime'] = $processedData[$processName]['endDateTime'];
                    }
                    else {
                        if($processType === 'BROWSER') {
                            $newSubProcessData = [
                                'url' => $url,
                                'startDateTime' => $timestamp,
                                'endDateTime' => $timestamp,
                                'title' => $data['Title'],
                                'productivityStatus' => Helper::computeActivityProductivityStatus(Helper::getDomainFromUrl($url),auth()->user()->id),
                            ];
                            array_push($subProcessData,$newSubProcessData);
                        }
                    }
                }

                // If the next process is different or this is the last entry, store the details and reset
                if (!isset($batchData[$key + 1]) || $batchData[$key + 1]['ProcessName'] !== $processName) {

                    $responseData[] = [
                        'process' => $processName,
                        'startDateTime' => $processedData[$processName]['startDateTime'],
                        'endDateTime' => $processedData[$processName]['endDateTime'],
                        'type' => $processedData[$processName]['type'],
                        'productivityStatus' => $processedData[$processName]['productivityStatus'],
                        'subProcess' => $subProcessData
                    ];
                    unset($processedData[$processName]);
                }
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
}

?>
