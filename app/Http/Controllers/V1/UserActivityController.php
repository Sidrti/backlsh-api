<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Helpers\Helper;
use App\Models\Process;
use App\Models\UserActivity;
use App\Models\UserSubActivity;
use DateTime;
use Illuminate\Http\Request;

class UserActivityController extends Controller
{
    public function createUserActivity(Request $request)
    {
      //  $validated = $request->validate([
        //    'Data' => 'required',
       // ]);
        if($request->all())
        {
            $userId = auth()->user()->id;

            $processedActivityData = $this->getActivityStartEndTime($request->all());
            
            foreach ($processedActivityData as $item) {
                foreach ($processedActivityData as $activity) {
                    
                    $process = Process::firstOrCreate([
                        'process_name' => $item['process'], 
                        'type' => 'BROWSER'
                    ]);
    
                    $userActivity = UserActivity::create([
                        'user_id' => $userId,
                        'process_id' => $process->id,
                        'productivity_status' => $activity['productivityStatus'],
                        'start_datetime' => $activity['startDateTime'],
                        'end_datetime' => $activity['endDateTime'],
                    ]);
                
                    // Insert sub-processes
                    foreach ($activity['subProcess'] as $subProcess) {
                        UserSubActivity::create([
                            'user_activity_id' => $userActivity->id,
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

        $response = [
            'StatusCode' => 1,
            'Data' => [],
            'Message' => 'Hurray !' 
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
            $preTimestamp = $data['DateTime'];
            $dateTime = new DateTime($preTimestamp);
            $timestamp = $dateTime->format('Y-m-d H:i:s');
            $url =  $data['Url'];
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
                            'productivityStatus' => Helper::computeSubActivityProductivityStatus($url),
                        ]
                    ];
                }
                $processedData[$processName] = [
                    'process' => $processName,
                    'startDateTime' => $timestamp,
                    'endDateTime' => $timestamp,
                    'type' => $processType,
                    'productivityStatus' => Helper::computeActivityProductivityStatus($processName),
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
                                'productivityStatus' => Helper::computeSubActivityProductivityStatus($url),
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
}

?>
