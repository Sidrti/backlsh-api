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
        $topMemberRequestOnly = $request->input('top_member_req_only', false);
        if ($topMemberRequestOnly) {
            $topMembers = $this->getTopMostProductiveMembers($userId, $request->input('top_member_days', 1));
            return response()->json(['status_code' => 1, 'data' => ['top_members' => $topMembers]]);
        }

        $totalHoursWorked = Helper::calculateTotalHoursByParentId($userId, $startDate, $endDate);
        $totalProductiveHours = Helper::calculateTotalHoursByParentId($userId, $startDate, $endDate, 'PRODUCTIVE');
        $totalNonProductiveHours = Helper::calculateTotalHoursByParentId($userId, $startDate, $endDate, 'NONPRODUCTIVE');
        $totalNeutralHours = Helper::calculateTotalHoursByParentId($userId, $startDate, $endDate, 'NEUTRAL');

        $teamWorkingTrend = $this->getProductiveNonProductiveTimeByEachDay($userId, $startDate, $endDate, true);

        $todayOnlineMemberCount = Helper::getMembersOnlineCount($userId);
        $todayTeamAttendance = $this->getTeamAttendanceToday($userId);
        $topMembers = $this->getTopMostProductiveMembers($userId, 1);

        $totalMembersInTeam = $this->getTotalTeamCount($userId);
        $weekProductivityPercentage = Helper::getWeeklyProductivityReport($userId);
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
            'week_productivity_percent' => $weekProductivityPercentage,
        ];
        return response()->json(['status_code' => 1, 'data' => $data]);
    }
    public function fetchUserDashboard(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subDays(7)->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $userId = auth()->user()->id;

        $totalHoursWorked = Helper::calculateTotalHoursByUserId($userId, $startDate, $endDate);
        $totalProductiveHours = Helper::calculateTotalHoursByUserId($userId, $startDate, $endDate, 'PRODUCTIVE');
        $totalNonProductiveHours = Helper::calculateTotalHoursByUserId($userId, $startDate, $endDate, 'NONPRODUCTIVE');
        $totalNeutralHours = Helper::calculateTotalHoursByUserId($userId, $startDate, $endDate, 'NEUTRAL');

        $userWorkingTrend = $this->getProductiveNonProductiveTimeByEachDay($userId, $startDate, $endDate, false);

        $monthUserAttendance = $this->getUserMonthAttendance($userId);
        $userThisMonthRank = $this->getUserRankOfmonth($userId);
        $userPeekHours = $this->getUserPeekHours($userId);
        $weekProductivityPercentage = Helper::getWeeklyProductivityReport($userId);

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
            'week_productivity_percent' => $weekProductivityPercentage,
        ];
        return response()->json(['status_code' => 1, 'data' => $data]);
    }
    private function getProductiveNonProductiveTimeByEachDay($userId, $startDate, $endDate, $teamRecords = true)
    {
        $userActivities = UserActivity::join('users', 'users.id', '=', 'user_activities.user_id')
            ->join('processes', 'processes.id', 'user_activities.process_id')
            ->where(function ($query) use ($userId, $teamRecords) {
                if ($teamRecords) {
                    $query->where('users.parent_user_id', $userId)
                        ->orWhere('users.id', $userId);
                } else {
                    $query->where('users.id', $userId);
                }
            })
            ->where('processes.process_name', '!=', '-1')
            ->where('processes.process_name', '!=', 'LockApp')
            ->where('processes.process_name', '!=', 'Idle')
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

        $userAttendance = Helper::getUserAttendance($userId, $firstDayOfMonth, $lastDayOfMonth);
        return $userAttendance;
    }
    private function getTeamAttendanceToday($userId)
    {
        $today = Carbon::today();

        $onlineMembersCount = UserActivity::whereDate('user_activities.start_datetime', $today)
            ->join('users', 'users.id', 'user_activities.user_id')
            ->where(function ($query) use ($userId) {
                $query->where('users.parent_user_id', $userId)
                    ->orWhere('users.id', $userId);
            })
            // ->orWhere('users.id',$userId)
            ->distinct('user_activities.user_id')
            ->count();

        return $onlineMembersCount;
    }
    private function getTopMostProductiveMembers($userId, $days = 1)
    {
        $endDate = Carbon::today()->format('Y-m-d');
        $startDate = Carbon::today()->subDays($days - 1)->format('Y-m-d');
        $minTimeThreshold = 1800 * $days; // Scale threshold with days (30min per day)

        $topMembers = DB::table('user_productivity_summaries')
            ->select(
                'user_productivity_summaries.user_id',
                'users.name',
                'users.email',
                'users.profile_picture',
                DB::raw('ROUND((SUM(productive_seconds) / NULLIF(SUM(total_seconds), 0)) * 100, 2) as productivity_percent'),
                DB::raw('SUM(productive_seconds) as productive_time'),
                DB::raw('SUM(total_seconds) as total_time'),
            )
            ->join('users', 'users.id', '=', 'user_productivity_summaries.user_id')
            ->whereBetween('date', [$startDate, $endDate])
            ->where(function ($query) use ($userId) {
                $query->where('users.parent_user_id', $userId)
                    ->orWhere('users.id', $userId);
            })
            ->groupBy('user_id', 'users.name', 'users.email', 'profile_picture')
            ->havingRaw('SUM(total_seconds) >= ?', [$minTimeThreshold])
            ->orderByDesc('productivity_percent')
            ->orderByDesc('productive_time') // Tie-breaker
            ->limit(5)
            ->get();

        // Format the time values for display
        $topMembers->transform(function ($member) {
            $member->productive_time = Helper::convertSecondsInReadableFormat($member->productive_time);
            $member->total_time = Helper::convertSecondsInReadableFormat($member->total_time);
            return $member;
        });
        return $topMembers;
        // return [
        //     'period' => $days === 1 ? 'Today' : "Last {$days} Days",
        //     'start_date' => $startDate,
        //     'end_date' => $endDate,
        //     'members' => $topMembers
        // ];
    }
    private function getTotalTeamCount($adminId)
    {
        return User::where('parent_user_id', $adminId)->count() + 1;
    }
    private function getUserRankOfmonth($userId)
    {
        $firstDayOfMonth = Carbon::now()->startOfMonth();
        $today = Carbon::today();

        $topMembers = UserActivity::select('user_id', DB::raw('SUM(CASE WHEN productivity_status = "productive" THEN 1 ELSE 0 END) as productive_count'))
            ->join('users', 'users.id', 'user_activities.user_id')
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
        if (count($userActivities) > 0) {
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

        $productivityTip = ProductivityTip::where('user_id', $userId)
            ->where('admin', true)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($productivityTip && $productivityTip->created_at->gt(now()->subHours(6))) {
            $lastRefreshed = 'Refreshed ' . $productivityTip->created_at->diffForHumans();
            return response()->json([
                'status_code' => 1,
                'data' => ['tip' => $productivityTip->tip, 'last_refreshed' => $lastRefreshed],
                'message' => 'Tip fetched from db'
            ]);
        }

        // Prepare optimized data for AI
        $analysisData = $this->prepareAiAnalysisData($userId);

        try {
            $prompt = $this->generateAiPrompt($analysisData);
            $response = $this->chatGptService->askChatGpt($prompt);
            $formatedResponse = $this->formatAIResponse($response);

            // Store the new tip
            $newTip = ProductivityTip::create([
                'user_id' => $userId,
                'tip' => $formatedResponse,
                'admin' => true,
            ]);

            return response()->json([
                'status_code' => 1,
                'data' => ['tip' => $formatedResponse, 'last_refreshed' => 'Refreshed just now'],
                'message' => 'Tip fetched from ChatGPT'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status_code' => 1,
                'data' => [
                    'tip' => 'Our AI systems are currently processing high workloads. Please try again shortly for detailed insights.',
                    'last_refreshed' => 'Refreshed just now'
                ],
                'message' => 'Generated fallback insights'
            ]);
        }
    }
    private function prepareAiAnalysisData($userId)
    {
        // 1. Aggregate key metrics
        $metrics = DB::table('user_activities')
            ->select(
                DB::raw('COUNT(DISTINCT user_id) as user_count'),
                DB::raw('SUM(TIMESTAMPDIFF(SECOND, start_datetime, end_datetime)) as total_seconds'),
                DB::raw('SUM(CASE WHEN productivity_status = "PRODUCTIVE" THEN TIMESTAMPDIFF(SECOND, start_datetime, end_datetime) ELSE 0 END) as productive_seconds'),
                DB::raw('ROUND(AVG(CASE WHEN productivity_status = "PRODUCTIVE" THEN 1 ELSE 0 END) * 100, 1) as productive_percent')
            )
            ->join('users', 'users.id', '=', 'user_activities.user_id')
            ->where(function ($query) use ($userId) {
                $query->where('users.parent_user_id', $userId)
                    ->orWhere('users.id', $userId);
            })
            // ->whereBetween('start_datetime', [$startOfWeek, $today])
            ->first();

        // 2. Get top productivity blockers (non-productive apps)
        $blockers = DB::table('user_activities')
            ->select(
                'processes.process_name',
                DB::raw('SUM(TIMESTAMPDIFF(SECOND, start_datetime, end_datetime)) as total_seconds'),
                DB::raw('COUNT(*) as occurrences')
            )
            ->join('processes', 'processes.id', '=', 'user_activities.process_id')
            ->join('users', 'users.id', '=', 'user_activities.user_id')
            ->where('productivity_status', 'NONPRODUCTIVE')
            ->where(function ($query) use ($userId) {
                $query->where('users.parent_user_id', $userId)
                    ->orWhere('users.id', $userId);
            })
            // ->whereBetween('start_datetime', [$startOfWeek, $today])
            ->groupBy('processes.id', 'processes.process_name')
            ->orderByDesc('total_seconds')
            ->limit(5)
            ->get();

        // 3. Get focus patterns (longest productive sessions)
        $focusSessions = DB::table('user_activities')
            ->select(
                'users.name as user_name',
                'processes.process_name',
                DB::raw('TIMESTAMPDIFF(MINUTE, start_datetime, end_datetime) as duration_minutes'),
                DB::raw('TIME(start_datetime) as start_time')
            )
            ->join('users', 'users.id', '=', 'user_activities.user_id')
            ->join('processes', 'processes.id', '=', 'user_activities.process_id')
            ->where('productivity_status', 'PRODUCTIVE')
            ->where(function ($query) use ($userId) {
                $query->where('users.parent_user_id', $userId)
                    ->orWhere('users.id', $userId);
            })
            // ->whereBetween('start_datetime', [$startOfWeek, $today])
            ->orderByDesc('duration_minutes')
            ->limit(3)
            ->get();

        return [
            'metrics' => $metrics,
            'productivity_blockers' => $blockers,
            'focus_patterns' => $focusSessions
        ];
    }
    public function fetchUserProductivityTips()
    {
        $userId = auth()->user()->id;

        $productvityTip = ProductivityTip::whereDate('created_at', Carbon::today())
            ->where('user_id', $userId)
            ->where('admin', false)
            ->first();

        if ($productvityTip) {
            $lastRefreshed = 'Refreshed ' . $productvityTip->created_at->diffForHumans();
            return response()->json(['status_code' => 1, 'data' => ['tip' => $productvityTip->tip, 'last_refreshed' => $lastRefreshed], 'message' => 'Tip fetched from db']);
        } else {
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
            Analyse the data and provide insights to the employer on how to improve teams productivity. Response should be neatly formatted.Use <h3></h3>,h4,h5 tags for headings. Only state the reasons for nonproductvity and tips to improve" . $userActivities;

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
            } catch (Exception $e) {
                $response = 'Our AI systems are very busy analysing your data, kindly give us some time. We will be back with deep insight';
            }
            return response()->json(['status_code' => 1, 'data' => ['tip' => $response, 'last_refreshed' => 'Refreshed Seconds ago'], 'message' => 'Tip fetched from chatgpt']);
        }
    }
    private function estimateTokens($text)
    {
        // Roughly estimate tokens based on word count and average token usage per word.
        $wordCount = str_word_count($text);
        $averageTokensPerWord = 1.3; // Adjust as needed
        return $wordCount * $averageTokensPerWord;
    }
    private function generateAiPrompt($analysisData)
    {
        $totalSeconds = Helper::convertSecondsInReadableFormat($analysisData['metrics']->total_seconds);
        return <<<EOT
            You are a productivity expert analyzing team work patterns from time tracking data. 
            
            IMPORTANT: Format your response in clean HTML with proper structure:
            - Use <h3> tags for main section headers
            - Use <ul> and <li> tags for lists
            - Use <p> tags for paragraphs
            - Use <strong> tags for emphasis
            - Do NOT use markdown syntax (**, -, #, etc.)
            
            Provide analysis in this exact structure:

            <h3>Executive Summary</h3>
            <p>Overall productivity score and key improvement/regression areas</p>

            <h3>Top Productivity Blockers</h3>
            <ul>
                <li>List top 3 time-wasting apps/behaviors with specific recommendations</li>
            </ul>

            <h3>Focus Patterns</h3>
            <p>Highlight most productive time blocks and schedule optimization recommendations</p>

            <h3>Actionable Recommendations</h3>
            <ul>
                <li>3 specific process improvements</li>
                <li>2 quick wins for immediate impact</li>
            </ul>

            Data Analysis:
            - Team Productivity: {$analysisData['metrics']->productive_percent}%
            - Total Tracked Time: {$totalSeconds}
            - Top Blockers: {$analysisData['productivity_blockers']->pluck('process_name')->implode(', ')}
            - Longest Focus Session: {$analysisData['focus_patterns'][0]->duration_minutes} mins on {$analysisData['focus_patterns'][0]->process_name}

            EOT;
    }

    private function formatAIResponse(string $response): string
    {
        // Remove any existing HTML tags that might interfere
        $formatted = strip_tags($response, '<h1><h2><h3><h4><h5><h6><p><ul><ol><li><strong><em><br><div><span>');

        // If the AI didn't use proper HTML, convert common patterns
        if (strpos($formatted, '<h3>') === false) {
            // Convert numbered headers to h3 tags
            $formatted = preg_replace('/^(\d+\.)\s*\*\*(.*?)\*\*$/m', '<h3>$2</h3>', $formatted);
            $formatted = preg_replace('/^#{1,3}\s*(.*?)$/m', '<h3>$1</h3>', $formatted);

            // Convert markdown lists to HTML lists
            $formatted = $this->convertMarkdownListsToHtml($formatted);

            // Convert **text** to <strong>text</strong>
            $formatted = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $formatted);

            // Convert paragraphs (double line breaks to <p> tags)
            $formatted = preg_replace('/\n\s*\n/', '</p><p>', $formatted);
            $formatted = '<p>' . $formatted . '</p>';

            // Fix empty paragraphs and clean up
            $formatted = preg_replace('/<p>\s*<\/p>/', '', $formatted);
            $formatted = preg_replace('/<p>\s*(<h[1-6]>.*?<\/h[1-6]>)\s*<\/p>/', '$1', $formatted);
        }

        // Clean up whitespace and ensure proper structure
        $formatted = preg_replace('/\s+/', ' ', $formatted);
        $formatted = str_replace(['<h3> ', ' </h3>'], ['<h3>', '</h3>'], $formatted);
        $formatted = str_replace(['<p> ', ' </p>'], ['<p>', '</p>'], $formatted);
        $formatted = str_replace(['<li> ', ' </li>'], ['<li>', '</li>'], $formatted);

        // Add proper spacing between sections for Vuetify
        $formatted = str_replace('</h3>', '</h3><div class="mb-3"></div>', $formatted);
        $formatted = str_replace('</ul>', '</ul><div class="mb-4"></div>', $formatted);
        $formatted = str_replace('</p>', '</p><div class="mb-2"></div>', $formatted);

        return trim($formatted);
    }

    private function convertMarkdownListsToHtml(string $text): string
    {
        $lines = explode("\n", $text);
        $result = [];
        $inList = false;
        $listItems = [];
        $i = 0;
        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Check if this is a list item
            if (preg_match('/^[-*+]\s+(.+)$/', $trimmed, $matches)) {
                if (!$inList) {
                    $inList = true;
                }
                $i++;
                $listItems[] = '<li>' . trim($matches[1]) . '</li>';
            } else {
                // Not a list item
                if ($inList) {
                    // Close the current list
                    $result[] = '<ul>' . implode('', $listItems) . '</ul>';
                    $listItems = [];
                    $inList = false;
                }

                if (!empty($trimmed)) {
                    $result[] = $line;
                }
            }
        }

        // Close any remaining list
        if ($inList && !empty($listItems)) {
            $result[] = '<ul>' . implode('', $listItems) . '</ul>';
        }

        return implode("\n", $result);
    }
}
