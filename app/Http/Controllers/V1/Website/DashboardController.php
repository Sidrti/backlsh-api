<?php

namespace App\Http\Controllers\V1\Website;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\ProductivityTip;
use App\Models\User;
use App\Models\UserActivity;
use App\Services\ChatGptService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    protected $chatGptService;

    // Use dependency injection to inject the ChatGptService
    public function __construct(ChatGptService $chatGptService)
    {
        $this->chatGptService = $chatGptService;
    }
    public function fetchAdminDashboard(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(7)->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $userId = auth()->user()->id;

        $totalHoursWorked = Helper::calculateTotalHoursByParentId($userId,$startDate,$endDate);
        $totalProductiveHours = Helper::calculateTotalHoursByParentId($userId,$startDate,$endDate,'PRODUCTIVE');
        $totalNonProductiveHours = Helper::calculateTotalHoursByParentId($userId,$startDate,$endDate,'NONPRODUCTIVE');
        $totalNeutralHours = Helper::calculateTotalHoursByParentId($userId,$startDate,$endDate,'NEUTRAL');

        $teamWorkingTrend = $this->getProductiveNonProductiveTimeByEachDay($userId,$startDate,$endDate,true);

        $todayOnlineMemberCount = Helper::getMembersOnlineCount($userId);
        $todayTeamAttendance = $this->getTeamAttendanceToday($userId);
        $topMembers = $this->getTopMostProductiveMembers($userId);

        $totalMembersInTeam = $this->getTotalTeamCount($userId);

       // $productivityTips = $this->getProductivityTips();

        $data =  [
            'total_time_worked' => $totalHoursWorked,
            'total_productive_hours' => $totalProductiveHours,
            'total_non_productive_hours' => $totalNonProductiveHours,
            'total_neutral_hours' => $totalNeutralHours,
            'team_working_trend' => $teamWorkingTrend,
            'today_online_member_count' => $todayOnlineMemberCount,
            'today_team_attendance' => $todayTeamAttendance,
            'top_members' => $topMembers,
            'total_members' => $totalMembersInTeam,
        ];
        return response()->json(['status_code' => 1, 'data' => $data]);
    }
    public function fetchUserDashboard(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(7)->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $userId = auth()->user()->id;

        $totalHoursWorked = Helper::calculateTotalHoursByUserId($userId,$startDate,$endDate);
        $totalProductiveHours = Helper::calculateTotalHoursByUserId($userId,$startDate,$endDate,'PRODUCTIVE');
        $totalNonProductiveHours = Helper::calculateTotalHoursByUserId($userId,$startDate,$endDate,'NONPRODUCTIVE');
        $totalNeutralHours = Helper::calculateTotalHoursByUserId($userId,$startDate,$endDate,'NEUTRAL');

        $userWorkingTrend = $this->getProductiveNonProductiveTimeByEachDay($userId,$startDate,$endDate,false);

        $monthUserAttendance = $this->getUserMonthAttendance($userId);
        $userThisMonthRank = $this->getUserRankOfmonth($userId);
        $userPeekHours = $this->getUserPeekHours($userId);

        //$productivityTips = $this->getProductivityTips();

        $data =  [
            'total_time_worked' => $totalHoursWorked,
            'total_productive_hours' => $totalProductiveHours,
            'total_non_productive_hours' => $totalNonProductiveHours,
            'total_neutral_hours' => $totalNeutralHours,
            'user_working_trend' => $userWorkingTrend,
            'month_user_attendance' => $monthUserAttendance,
            'month_user_rank' => $userThisMonthRank,
            'user_peek_hours' => $userPeekHours,
        ];
        return response()->json(['status_code' => 1, 'data' => $data]);
    }
    private function getProductiveNonProductiveTimeByEachDay($userId, $startDate, $endDate, $teamRecords = true)
    {
        $userActivities = UserActivity::join('users', 'users.id', '=', 'user_activities.user_id')
        ->join('processes','processes.id','user_activities.process_id')
        ->where(function ($query) use ($userId,$teamRecords) {
            if ($teamRecords) {
                $query->where('users.parent_user_id', $userId)
                      ->orWhere('users.id', $userId);
            } else {
                $query->where('users.id', $userId);
            }
            
        })
        ->where('processes.process_name','!=','-1')
        ->where('processes.process_name','!=','LockApp')
        ->where('processes.process_name','!=','Idle')
        ->whereRaw("DATE(user_activities.start_datetime) BETWEEN ? AND ?", [$startDate, $endDate])
        ->whereRaw("DATE(user_activities.end_datetime) BETWEEN ? AND ?", [$startDate, $endDate])
        ->whereRaw("DATE(user_activities.start_datetime) = DATE(user_activities.end_datetime)") 
        ->get();

   //     return $userActivities;
        $result = [];
        $nonProductiveHours = 0;
        $neutralHours = 0; 
        $productiveHours = 0;
        foreach ($userActivities as $activity) {
            $date = Carbon::parse($activity->start_datetime)->format('d-m-Y');
            $endDate = Carbon::parse($activity->end_datetime);
            $hours = Carbon::parse($activity->end_datetime)->diffInSeconds(Carbon::parse($activity->start_datetime)) / 3600;
        
            switch ($activity->productivity_status) {
                case 'PRODUCTIVE':
                    $productiveHours = $hours;
                    break;
                case 'NONPRODUCTIVE':
                    $nonProductiveHours = $hours;
                    break;
                case 'NEUTRAL':
                    $neutralHours = $hours;
                    break;
                default:
                    break;
            }

            if (!isset($result[$date])) {
                $result[$date] = [
                    'date' => $date,
                    'productive' => $productiveHours,
                    'nonproductive' => $nonProductiveHours,
                    'neutral' => $neutralHours,
                ];
            } else {
                $result[$date]['productive'] += $productiveHours;
                $result[$date]['nonproductive'] += $nonProductiveHours;
                $result[$date]['neutral'] += $neutralHours;
            }
            $nonProductiveHours = 0;
            $neutralHours = 0; 
            $productiveHours = 0;
            $hours = 0;

        }

        foreach ($result as &$day) {
            $day['productive'] = round($day['productive'], 1);
            $day['productive_tooltip'] = Helper::convertSecondsInReadableFormat($day['productive'] * 3600);
            $day['nonproductive'] = round($day['nonproductive'], 1);
            $day['nonproductive_tooltip'] = Helper::convertSecondsInReadableFormat($day['nonproductive'] * 3600);
            $day['neutral'] = round($day['neutral'], 1);
            $day['neutral_tooltip'] = Helper::convertSecondsInReadableFormat($day['neutral'] * 3600);
        }

        return array_values($result);
    }

    private function getUserMonthAttendance($userId)
    {
        $firstDayOfMonth = Carbon::now()->startOfMonth();
        $lastDayOfMonth = Carbon::today();

        $userAttendance = Helper::getUserAttendance($userId,$firstDayOfMonth,$lastDayOfMonth);
        return $userAttendance;
    }
    private function getTeamAttendanceToday($userId)
    {
        $today = Carbon::today();

        $onlineMembersCount = UserActivity::whereDate('user_activities.start_datetime', $today)
            ->join('users','users.id','user_activities.user_id')
            ->where(function ($query) use ($userId) {
                $query->where('users.parent_user_id', $userId)
                      ->orWhere('users.id', $userId);
            })
           // ->orWhere('users.id',$userId)
            ->distinct('user_activities.user_id')
            ->count();
    
        return $onlineMembersCount;
    }
    private function getTopMostProductiveMembers($userId)
    {
        $today = Carbon::today();
        //$today = '2024-02-05';
        $topMembers = UserActivity::select('user_id', 'users.name','users.email', DB::raw('SUM(CASE WHEN productivity_status = "productive" THEN 1 ELSE 0 END) as productive_count'),  DB::raw("CONCAT('" . config('app.media_base_url') . "', users.profile_picture) as profile_picture"))
            ->join('users','users.id','user_activities.user_id')
            ->whereDate('start_datetime', $today)
            ->where(function ($query) use ($userId) {
                $query->where('users.parent_user_id', $userId)
                    ->orWhere('users.id', $userId);
            })
            ->groupBy('user_id', 'users.name', 'users.email','profile_picture')
            ->orderByDesc('productive_count')
            ->limit(5)
            ->get();

        return $topMembers;
    }
    private function getTotalTeamCount($adminId) 
    {
        return User::where('parent_user_id',$adminId)->count() + 1;
    }
    private function getUserRankOfmonth($userId)
    {
        $firstDayOfMonth = Carbon::now()->startOfMonth();
        $today = Carbon::today();

        $topMembers = UserActivity::select('user_id', DB::raw('SUM(CASE WHEN productivity_status = "productive" THEN 1 ELSE 0 END) as productive_count'))
            ->join('users','users.id','user_activities.user_id')
            ->where(function ($query) use ($userId) {
                $query->where('users.parent_user_id', $userId)
                    ->orWhere('users.id', $userId);
            })
            ->whereRaw("DATE(user_activities.start_datetime) BETWEEN ? AND ?", [$firstDayOfMonth, $today])
            ->groupBy('user_id')
            ->orderByDesc('productive_count')
            ->get();

            $index = 1;
            foreach ($topMembers as $member) {
                if ($member->user_id === $userId) {
                    return $index;
                }
                $index++;
            }
            return null;
    }
    private function getUserPeekHours($userId)
    {
        $startDate = Carbon::now()->subDays(10)->startOfDay();
        $endDate = Carbon::now()->endOfDay();
    
        $userActivities = UserActivity::where('user_id', $userId)
            ->whereBetween('start_datetime', [$startDate, $endDate])
            ->where('productivity_status', 'productive')
            ->get();
    
        // Initialize an array to store productivity counts for each hour
        $hourlyProductivity = array_fill(0, 24, 0);
    
        // Iterate through the user activities and update the hourly productivity counts
        foreach ($userActivities as $activity) {
            $startHour = Carbon::parse($activity->start_datetime)->hour;
            $endHour = Carbon::parse($activity->end_datetime)->hour;
    
            // Increment productivity counts for each hour within the activity duration
            for ($hour = $startHour; $hour <= $endHour; $hour++) {
                $hourlyProductivity[$hour]++;
            }
        }
    
        // Find the peak hours with highest productivity counts
        $maxProductivity = max($hourlyProductivity);
        $peakHours = [];
        if(count($userActivities) > 0) {
            for ($hour = 0; $hour < 24; $hour++) {
                if ($hourlyProductivity[$hour] === $maxProductivity) {
                    // Format hours with AM/PM
                    $startHourFormatted = $hour >= 12 ? ($hour === 12 ? '12 PM' : ($hour - 12) . ' PM') : ($hour === 0 ? '12 AM' : $hour . ' AM');
                    $endHourFormatted = (($hour + 1) % 24) >= 12 ? ((($hour + 1) % 24) === 12 ? '12 PM' : ((($hour + 1) % 24) - 12) . ' PM') : ((($hour + 1) % 24) === 0 ? '12 AM' : (($hour + 1) % 24) . ' AM');
        
                    // Construct the formatted peak hour string
                    $peakHours[] = "$startHourFormatted - $endHourFormatted";
                }
            }
        
        }

        return $peakHours;
    }  
    public function fetchAdminProductivityTips()
    {
        $userId = auth()->user()->id;

        $productvityTip = ProductivityTip::whereDate('created_at',Carbon::today())
        ->where('user_id',$userId)
        ->where('admin',true)
        ->first();

        if($productvityTip) {
            $lastRefreshed = 'Refreshed '.$productvityTip->created_at->diffForHumans();
            return response()->json(['status_code' => 1, 'data' => ['tip' => $productvityTip->tip,'last_refreshed' => $lastRefreshed], 'message' => 'Tip fetched from db']);
        }
        else {
            $userActivities = UserActivity::join('users', 'users.id', '=', 'user_activities.user_id')
            ->join('processes', 'processes.id', '=', 'user_activities.process_id')
            ->where('users.parent_user_id', $userId)
            ->orWhere('users.id', $userId)
            ->select(
                'users.name as user_name',
                'users.email as user_email',
                'processes.process_name',
                'user_activities.start_datetime as process_start_time',
                'user_activities.end_datetime as process_end_time',
                'user_activities.productivity_status'
            )
            ->orderBy('user_activities.created_at', 'desc') // Latest entries first
            ->get();
    
            $estimatedTokens = $this->estimateTokens($userActivities->toJson());
    
            // Define the token limit (assume 3500 for safety margin)
            $tokenLimit = 3000;
        
            // Trim data if it exceeds the token limit
            if ($estimatedTokens > $tokenLimit) {
                // Trimming logic: limit the number of user activities
                $maxEntries = intval($tokenLimit / $estimatedTokens * count($userActivities));
                $userActivities = $userActivities->take($maxEntries);
            }
            $prompt = "You are a data analyst at Time Tracking company like Timedoctor that collects data from employee computer on what apps and websites they are working.
            Analyse the data and provide insights to the employer on how to improve teams productivity. Response should be neatly formatted.Use <h3></h3>,h4,h5 tags for headings. Only state the reasons for nonproductvity and tips to improve". $userActivities ;

            try {
                $response = $this->chatGptService->askChatGpt($prompt);
                $response = str_replace("\n\n", "\n", $response);
                $response = str_replace("\n\n\n", "\n", $response);
                $response = nl2br($response);
                ProductivityTip::create([
                    'user_id' => $userId,
                    'tip' => $response,
                    'admin' => true,
                ]);
            }
            catch(Exception $e) {
                $response = 'Our AI systems are very busy analysing your data, kindly give us some time. We will be back with deep insight';
            }
        
            return response()->json(['status_code' => 1, 'data' => ['tip' =>$response, 'last_refreshed' =>'Refreshed Seconds ago'],'message' => 'Tip fetched from chatgpt']);
        }
    } 
    public function fetchUserProductivityTips()
    {
        $userId = auth()->user()->id;

        $productvityTip = ProductivityTip::whereDate('created_at',Carbon::today())
        ->where('user_id',$userId)
        ->where('admin',false)
        ->first();

        if($productvityTip) {
            $lastRefreshed = 'Refreshed '.$productvityTip->created_at->diffForHumans();
            return response()->json(['status_code' => 1, 'data' => ['tip' => $productvityTip->tip,'last_refreshed' => $lastRefreshed], 'message' => 'Tip fetched from db']);
        }
        else {
            $userActivities = UserActivity::join('users', 'users.id', '=', 'user_activities.user_id')
            ->join('processes', 'processes.id', '=', 'user_activities.process_id')
            ->where('users.id', $userId)
            
            ->select(
                'users.name as user_name',
                'users.email as user_email',
                'processes.process_name',
                'user_activities.start_datetime as process_start_time',
                'user_activities.end_datetime as process_end_time',
                'user_activities.productivity_status'
            )
            ->orderBy('user_activities.created_at', 'desc') // Latest entries first
            ->get();
    
            $estimatedTokens = $this->estimateTokens($userActivities->toJson());
    
            // Define the token limit (assume 3500 for safety margin)
            $tokenLimit = 3500;
        
            // Trim data if it exceeds the token limit
            if ($estimatedTokens > $tokenLimit) {
                // Trimming logic: limit the number of user activities
                $maxEntries = intval($tokenLimit / $estimatedTokens * count($userActivities));
                $userActivities = $userActivities->take($maxEntries);
            }
            $prompt = "You are a data analyst at Time Tracking company like Timedoctor that collects data from employee computer on what apps and websites they are working.
            Analyse the data and provide insights to the employer on how to improve teams productivity. Response should be neatly formatted.Use <h3></h3>,h4,h5 tags for headings. Only state the reasons for nonproductvity and tips to improve". $userActivities ;
           
            try {
                $response = $this->chatGptService->askChatGpt($prompt);
               // $response = str_replace("\n\n", "\n", $response);
                $response = str_replace("\n\n\n", "\n\n", $response);
                $response = nl2br($response);
                ProductivityTip::create([
                    'user_id' => $userId,
                    'tip' => $response,
                    'admin' => false,
                ]);
            }
            catch(Exception $e) {
                $response = 'Our AI systems are very busy analysing your data, kindly give us some time. We will be back with deep insight';
            }
            return response()->json(['status_code' => 1, 'data' => ['tip' =>$response,'last_refreshed' =>'Refreshed Seconds ago'],'message' => 'Tip fetched from chatgpt']);
        }
    } 
    private function estimateTokens($text) {
        // Roughly estimate tokens based on word count and average token usage per word.
        $wordCount = str_word_count($text);
        $averageTokensPerWord = 1.3; // Adjust as needed
        return $wordCount * $averageTokensPerWord;
    }
}