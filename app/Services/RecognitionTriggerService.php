<?php

namespace App\Services;

use App\Helpers\Helper;
use App\Models\AttendanceSchedule;
use App\Models\Notification;
use App\Models\Recognition;
use App\Models\RecognitionPrompt;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\UserProductivitySummary;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecognitionTriggerService
{
    /**
     * Cooldown period in days between recognition prompts for the same employee
     */
    protected int $cooldownDays = 7;

    /**
     * Evaluate all 17 recognition triggers for a company / team.
     *
     * @param int $adminId
     * @return array Summary of generated prompts
     */
    public function evaluateAllTriggersForTeam(int $adminId): array
    {
        $admin = User::find($adminId);
        if (!$admin) {
            return ['status' => 0, 'message' => 'Admin not found'];
        }

        $teamUsers = User::where('parent_user_id', $adminId)
            ->where('id', '!=', $adminId)
            ->whereIn('status', [1, '1', 'ACTIVE'])
            ->get();

        if ($teamUsers->isEmpty()) {
            return ['status' => 1, 'dispatched' => 0, 'message' => 'No active team members'];
        }

        $teamUserIds = $teamUsers->pluck('id')->toArray();

        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $generatedPrompts = [];

        // 1. Team-wide comparative signals evaluated first
        $mostImproved = $this->checkMostImprovedProductiveHours($teamUserIds, $weekStart);
        if ($mostImproved && !$this->isCandidateInCooldown($mostImproved['candidate_id'])) {
            $candidate = $teamUsers->firstWhere('id', $mostImproved['candidate_id']);
            if ($candidate) {
                $this->dispatchPromptNotification(
                    $admin,
                    $candidate,
                    'REL_MOST_IMPROVED_WOW',
                    "👏 Most Improved: {$candidate->name}",
                    $mostImproved['message'],
                    'GREAT_WORK'
                );
                $generatedPrompts[] = "REL_MOST_IMPROVED_WOW for {$candidate->name}";
            }
        }

        $lowestVariance = $this->checkLowestDailyVariance($teamUserIds, $monthStart, $monthEnd);
        if ($lowestVariance && !$this->isCandidateInCooldown($lowestVariance['candidate_id'])) {
            $candidate = $teamUsers->firstWhere('id', $lowestVariance['candidate_id']);
            if ($candidate) {
                $this->dispatchPromptNotification(
                    $admin,
                    $candidate,
                    'REL_LOWEST_DAILY_VARIANCE_TEAM',
                    "🎯 Most Dependable Schedule: {$candidate->name}",
                    $lowestVariance['message'],
                    'CONSISTENT_EFFORT'
                );
                $generatedPrompts[] = "REL_LOWEST_DAILY_VARIANCE_TEAM for {$candidate->name}";
            }
        }

        // 2. Individual member signals
        foreach ($teamUsers as $user) {
            if ($this->isCandidateInCooldown($user->id)) {
                continue;
            }

            $prompt = null;

            // Priority 1: Recovery / Turnaround Signals
            $prompt = $prompt ?: $this->checkProductiveTimeRebound($user, $weekStart);
            $prompt = $prompt ?: $this->checkAttendanceRecovery($user, $weekStart);

            // Priority 2: Volume / Personal Highs
            $prompt = $prompt ?: $this->checkWeeklyHoursPersonalHigh($user, $weekStart, $weekEnd);
            $prompt = $prompt ?: $this->checkMonthlyHoursPersonalHigh($user, $monthStart, $monthEnd);
            $prompt = $prompt ?: $this->checkHoursAboveTeamAverageStreak($user, $teamUserIds);
            $prompt = $prompt ?: $this->checkHealthyHoursConsistency($user, $monthStart, $monthEnd);

            // Priority 3: Productivity Quality
            $prompt = $prompt ?: $this->checkProductivityPercentPersonalBest($user, $weekStart, $weekEnd);
            $prompt = $prompt ?: $this->checkProductivityPercentAboveTeamAvg($user, $teamUserIds, $weekStart, $weekEnd);
            $prompt = $prompt ?: $this->checkUnproductiveTimeDrop($user, $weekStart);
            $prompt = $prompt ?: $this->checkSustainedProductivityImprovement($user);

            // Priority 4: Consistency / Attendance
            $prompt = $prompt ?: $this->checkWeeklyAttendance90($user, $weekStart, $weekEnd);
            $prompt = $prompt ?: $this->checkMonthlyAttendance90($user, $monthStart, $monthEnd);

            // Priority 5: Tenure & Milestones
            $prompt = $prompt ?: $this->checkClosingTheGap($user, $teamUserIds, $weekStart);
            $prompt = $prompt ?: $this->checkFirstMonthConsistency($user);
            $prompt = $prompt ?: $this->checkWorkAnniversaryAndMilestone($user);

            if ($prompt) {
                $this->dispatchPromptNotification(
                    $admin,
                    $user,
                    $prompt['trigger_key'],
                    $prompt['title'],
                    $prompt['message'],
                    $prompt['suggested_preset']
                );
                $generatedPrompts[] = "{$prompt['trigger_key']} for {$user->name}";
            }
        }

        return [
            'status' => 1,
            'dispatched_count' => count($generatedPrompts),
            'prompts' => $generatedPrompts,
        ];
    }

    /**
     * Check if a candidate is within the anti-fatigue cooldown period
     */
    public function isCandidateInCooldown(int $candidateUserId): bool
    {
        return RecognitionPrompt::where('candidate_user_id', $candidateUserId)
            ->where('created_at', '>=', Carbon::now()->subDays($this->cooldownDays))
            ->exists();
    }

    /**
     * Trigger 1: Weekly hours cross a personal high (best in past 4 weeks)
     */
    public function checkWeeklyHoursPersonalHigh(User $user, Carbon $weekStart, Carbon $weekEnd): ?array
    {
        $currentWeekSeconds = UserProductivitySummary::where('user_id', $user->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->sum('total_seconds');

        // Minimum 25 hours threshold for personal high
        if ($currentWeekSeconds < 25 * 3600) {
            return null;
        }

        $pastMaxSeconds = 0;
        for ($i = 1; $i <= 4; $i++) {
            $pwStart = $weekStart->copy()->subWeeks($i);
            $pwEnd = $weekEnd->copy()->subWeeks($i);
            $wSeconds = UserProductivitySummary::where('user_id', $user->id)
                ->whereBetween('date', [$pwStart->toDateString(), $pwEnd->toDateString()])
                ->sum('total_seconds');
            if ($wSeconds > $pastMaxSeconds) {
                $pastMaxSeconds = $wSeconds;
            }
        }

        if ($pastMaxSeconds > 0 && $currentWeekSeconds > $pastMaxSeconds) {
            $hours = round($currentWeekSeconds / 3600, 1);
            return [
                'trigger_key' => 'VOL_WEEKLY_HOURS_PERSONAL_HIGH',
                'title' => "🚀 New Weekly High for {$user->name}",
                'message' => "{$user->name} logged {$hours}h this week — their highest weekly total in the past 4 weeks! Send a quick shoutout.",
                'suggested_preset' => 'ABOVE_AND_BEYOND',
            ];
        }

        return null;
    }

    /**
     * Trigger 2: Monthly hours cross a personal high (past 6 months)
     */
    public function checkMonthlyHoursPersonalHigh(User $user, Carbon $monthStart, Carbon $monthEnd): ?array
    {
        $currentMonthSeconds = UserProductivitySummary::where('user_id', $user->id)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('total_seconds');

        if ($currentMonthSeconds < 100 * 3600) {
            return null;
        }

        $pastMaxSeconds = 0;
        for ($i = 1; $i <= 6; $i++) {
            $pmStart = $monthStart->copy()->subMonths($i);
            $pmEnd = $monthEnd->copy()->subMonths($i);
            $mSeconds = UserProductivitySummary::where('user_id', $user->id)
                ->whereBetween('date', [$pmStart->toDateString(), $pmEnd->toDateString()])
                ->sum('total_seconds');
            if ($mSeconds > $pastMaxSeconds) {
                $pastMaxSeconds = $mSeconds;
            }
        }

        if ($pastMaxSeconds > 0 && $currentMonthSeconds > $pastMaxSeconds) {
            $hours = round($currentMonthSeconds / 3600, 1);
            return [
                'trigger_key' => 'VOL_MONTHLY_HOURS_PERSONAL_HIGH',
                'title' => "🌟 Personal Best Month: {$user->name}",
                'message' => "{$user->name} reached a personal high of {$hours}h tracked this month! Acknowledge their dedication.",
                'suggested_preset' => 'CONSISTENT_EFFORT',
            ];
        }

        return null;
    }

    /**
     * Trigger 3: Hours logged above team average for 2+ consecutive weeks
     */
    public function checkHoursAboveTeamAverageStreak(User $user, array $teamUserIds, int $weeks = 2): ?array
    {
        if (count($teamUserIds) < 2) {
            return null;
        }

        for ($i = 0; $i < $weeks; $i++) {
            $wStart = Carbon::now()->startOfWeek()->subWeeks($i);
            $wEnd = Carbon::now()->endOfWeek()->subWeeks($i);

            $teamTotalSeconds = UserProductivitySummary::whereIn('user_id', $teamUserIds)
                ->whereBetween('date', [$wStart->toDateString(), $wEnd->toDateString()])
                ->sum('total_seconds');
            $teamAvg = $teamTotalSeconds / count($teamUserIds);

            $userSeconds = UserProductivitySummary::where('user_id', $user->id)
                ->whereBetween('date', [$wStart->toDateString(), $wEnd->toDateString()])
                ->sum('total_seconds');

            if ($userSeconds <= ($teamAvg * 1.05)) {
                return null;
            }
        }

        return [
            'trigger_key' => 'VOL_HOURS_ABOVE_TEAM_AVG_STREAK',
            'title' => "💪 Solid Pace: {$user->name}",
            'message' => "{$user->name} has logged above the team average for 2+ consecutive weeks. Let them know it's noticed!",
            'suggested_preset' => 'CONSISTENT_EFFORT',
        ];
    }

    /**
     * Trigger 4: Hours logged consistently within healthy range for a full month
     */
    public function checkHealthyHoursConsistency(User $user, Carbon $monthStart, Carbon $monthEnd): ?array
    {
        $weeklySummaries = [];
        for ($i = 0; $i < 4; $i++) {
            $wStart = $monthEnd->copy()->subWeeks($i)->startOfWeek();
            $wEnd = $monthEnd->copy()->subWeeks($i)->endOfWeek();

            $wSeconds = UserProductivitySummary::where('user_id', $user->id)
                ->whereBetween('date', [$wStart->toDateString(), $wEnd->toDateString()])
                ->sum('total_seconds');
            $hours = $wSeconds / 3600;

            // Healthy range: 35h to 45h per week
            if ($hours < 35 || $hours > 48) {
                return null;
            }
            $weeklySummaries[] = $hours;
        }

        return [
            'trigger_key' => 'VOL_STEADY_HEALTHY_HOURS_MONTH',
            'title' => "🌱 Steady & Dependable: {$user->name}",
            'message' => "{$user->name} maintained a steady, healthy 38–44h/week schedule throughout the entire month — reliable and consistent.",
            'suggested_preset' => 'CONSISTENT_EFFORT',
        ];
    }

    /**
     * Trigger 5: Weekly attendance >= 90%
     */
    public function checkWeeklyAttendance90(User $user, Carbon $weekStart, Carbon $weekEnd): ?array
    {
        $attendance = Helper::getUserAttendance($user->id, $weekStart->copy(), $weekEnd->copy());
        $daysPresent = $attendance['days_present'] ?? 0;
        $totalDays = $attendance['total_days'] ?? 7;

        // Assuming 5 working days in a standard week
        if ($daysPresent >= 4.5) {
            return [
                'trigger_key' => 'REL_ATTENDANCE_WEEK_90',
                'title' => "🎯 Great Attendance: {$user->name}",
                'message' => "{$user->name} achieved ≥90% attendance this week! Consistently showing up for the team.",
                'suggested_preset' => 'CONSISTENT_EFFORT',
            ];
        }

        return null;
    }

    /**
     * Trigger 6: Monthly attendance > 90%
     */
    public function checkMonthlyAttendance90(User $user, Carbon $monthStart, Carbon $monthEnd): ?array
    {
        $attendance = Helper::getUserAttendance($user->id, $monthStart->copy(), $monthEnd->copy());
        $daysPresent = $attendance['days_present'] ?? 0;
        $totalDays = $attendance['total_days'] ?? 30;

        if ($totalDays > 0 && ($daysPresent / $totalDays) > 0.90 && $daysPresent >= 18) {
            return [
                'trigger_key' => 'REL_ATTENDANCE_MONTH_90',
                'title' => "🌟 Perfect Consistency: {$user->name}",
                'message' => "{$user->name} achieved >90% attendance across the entire month! Celebrate their consistency.",
                'suggested_preset' => 'CONSISTENT_EFFORT',
            ];
        }

        return null;
    }

    /**
     * Trigger 7: Lowest variance in daily hours across the team for the month
     */
    public function checkLowestDailyVariance(array $teamUserIds, Carbon $monthStart, Carbon $monthEnd): ?array
    {
        $minStdDev = null;
        $bestUserId = null;

        foreach ($teamUserIds as $uId) {
            $dailyHours = UserProductivitySummary::where('user_id', $uId)
                ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->where('total_seconds', '>', 3600)
                ->pluck('total_seconds')
                ->map(fn($sec) => $sec / 3600)
                ->toArray();

            if (count($dailyHours) < 10) {
                continue;
            }

            $mean = array_sum($dailyHours) / count($dailyHours);
            $variance = 0.0;
            foreach ($dailyHours as $val) {
                $variance += pow($val - $mean, 2);
            }
            $stdDev = sqrt($variance / count($dailyHours));

            if ($minStdDev === null || $stdDev < $minStdDev) {
                $minStdDev = $stdDev;
                $bestUserId = $uId;
            }
        }

        if ($bestUserId && $minStdDev !== null && $minStdDev < 1.2) {
            return [
                'candidate_id' => $bestUserId,
                'message' => "had the lowest daily hours variance across the team this month — the most dependable schedule.",
            ];
        }

        return null;
    }

    /**
     * Trigger 8: Productive-hours percentage crosses personal best
     */
    public function checkProductivityPercentPersonalBest(User $user, Carbon $weekStart, Carbon $weekEnd): ?array
    {
        $current = UserProductivitySummary::where('user_id', $user->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->selectRaw('SUM(productive_seconds) as prod, SUM(total_seconds) as total')
            ->first();

        if (!$current || !$current->total || $current->total < 20 * 3600) {
            return null;
        }

        $currentPercent = round(($current->prod / $current->total) * 100);
        if ($currentPercent < 78) {
            return null;
        }

        $pastTotalProd = 0;
        $pastTotalSec = 0;
        for ($i = 1; $i <= 4; $i++) {
            $wS = $weekStart->copy()->subWeeks($i);
            $wE = $weekEnd->copy()->subWeeks($i);
            $past = UserProductivitySummary::where('user_id', $user->id)
                ->whereBetween('date', [$wS->toDateString(), $wE->toDateString()])
                ->selectRaw('SUM(productive_seconds) as prod, SUM(total_seconds) as total')
                ->first();
            if ($past) {
                $pastTotalProd += $past->prod;
                $pastTotalSec += $past->total;
            }
        }

        if ($pastTotalSec > 0) {
            $pastAvgPercent = round(($pastTotalProd / $pastTotalSec) * 100);
            if ($currentPercent >= $pastAvgPercent + 7) {
                return [
                    'trigger_key' => 'PROD_PERCENT_PERSONAL_BEST',
                    'title' => "⚡ Productivity High: {$user->name}",
                    'message' => "{$user->name}'s productive time reached {$currentPercent}% this week (up from typical {$pastAvgPercent}%)! Great focus.",
                    'suggested_preset' => 'GREAT_WORK',
                ];
            }
        }

        return null;
    }

    /**
     * Trigger 9: Productive-hours percentage above team average for the week
     */
    public function checkProductivityPercentAboveTeamAvg(User $user, array $teamUserIds, Carbon $weekStart, Carbon $weekEnd): ?array
    {
        if (count($teamUserIds) < 2) {
            return null;
        }

        $team = UserProductivitySummary::whereIn('user_id', $teamUserIds)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->selectRaw('SUM(productive_seconds) as prod, SUM(total_seconds) as total')
            ->first();

        $userSummary = UserProductivitySummary::where('user_id', $user->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->selectRaw('SUM(productive_seconds) as prod, SUM(total_seconds) as total')
            ->first();

        if (!$team || !$team->total || !$userSummary || !$userSummary->total || $userSummary->total < 20 * 3600) {
            return null;
        }

        $teamPercent = ($team->prod / $team->total) * 100;
        $userPercent = ($userSummary->prod / $userSummary->total) * 100;

        if ($userPercent >= $teamPercent + 12 && $userPercent >= 80) {
            $diff = round($userPercent - $teamPercent);
            return [
                'trigger_key' => 'PROD_PERCENT_ABOVE_TEAM_AVG',
                'title' => "🏆 High Focus: {$user->name}",
                'message' => "{$user->name}'s productive hours ratio was {$diff}% above team average this week.",
                'suggested_preset' => 'GREAT_WORK',
            ];
        }

        return null;
    }

    /**
     * Trigger 10: Unproductive/neutral time drops significantly WoW
     */
    public function checkUnproductiveTimeDrop(User $user, Carbon $weekStart): ?array
    {
        $prevWeekStart = $weekStart->copy()->subWeek();
        $prevWeekEnd = $weekStart->copy()->subSecond();
        $currWeekEnd = $weekStart->copy()->endOfWeek();

        $prev = UserProductivitySummary::where('user_id', $user->id)
            ->whereBetween('date', [$prevWeekStart->toDateString(), $prevWeekEnd->toDateString()])
            ->selectRaw('SUM(nonproductive_seconds + neutral_seconds) as unprod, SUM(total_seconds) as total')
            ->first();

        $curr = UserProductivitySummary::where('user_id', $user->id)
            ->whereBetween('date', [$weekStart->toDateString(), $currWeekEnd->toDateString()])
            ->selectRaw('SUM(nonproductive_seconds + neutral_seconds) as unprod, SUM(total_seconds) as total')
            ->first();

        if ($prev && $curr && $prev->unprod > 5 * 3600 && $curr->total >= 20 * 3600) {
            $dropRate = ($prev->unprod - $curr->unprod) / $prev->unprod;
            if ($dropRate >= 0.25) {
                $pct = round($dropRate * 100);
                return [
                    'trigger_key' => 'PROD_UNPRODUCTIVE_DROP_WOW',
                    'title' => "📈 Distraction Cut: {$user->name}",
                    'message' => "{$user->name} cut unproductive & neutral time by {$pct}% week-over-week. Significant focus improvement!",
                    'suggested_preset' => 'GREAT_WORK',
                ];
            }
        }

        return null;
    }

    /**
     * Trigger 11: Sustained productive-time improvement over 3+ weeks
     */
    public function checkSustainedProductivityImprovement(User $user): ?array
    {
        $weeks = [];
        for ($i = 0; $i < 3; $i++) {
            $wS = Carbon::now()->startOfWeek()->subWeeks($i);
            $wE = Carbon::now()->endOfWeek()->subWeeks($i);
            $prod = UserProductivitySummary::where('user_id', $user->id)
                ->whereBetween('date', [$wS->toDateString(), $wE->toDateString()])
                ->sum('productive_seconds');
            $weeks[] = $prod;
        }

        // $weeks[0] = this week, $weeks[1] = last week, $weeks[2] = 2 weeks ago
        if ($weeks[0] > $weeks[1] && $weeks[1] > $weeks[2] && $weeks[2] > 10 * 3600) {
            return [
                'trigger_key' => 'PROD_SUSTAINED_IMPROVEMENT_3W',
                'title' => "🔥 3-Week Rising Streak: {$user->name}",
                'message' => "{$user->name} has increased productive output for 3 consecutive weeks in a row!",
                'suggested_preset' => 'ABOVE_AND_BEYOND',
            ];
        }

        return null;
    }

    /**
     * Trigger 12: Productive time rebounds after a prior dip
     */
    public function checkProductiveTimeRebound(User $user, Carbon $weekStart): ?array
    {
        $curr = UserProductivitySummary::where('user_id', $user->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekStart->copy()->endOfWeek()->toDateString()])
            ->sum('productive_seconds');

        $dipWeek = UserProductivitySummary::where('user_id', $user->id)
            ->whereBetween('date', [$weekStart->copy()->subWeek()->toDateString(), $weekStart->copy()->subWeek()->endOfWeek()->toDateString()])
            ->sum('productive_seconds');

        $baseline = UserProductivitySummary::where('user_id', $user->id)
            ->whereBetween('date', [$weekStart->copy()->subWeeks(2)->toDateString(), $weekStart->copy()->subWeeks(2)->endOfWeek()->toDateString()])
            ->sum('productive_seconds');

        if ($baseline > 20 * 3600 && $dipWeek < ($baseline * 0.75) && $curr >= ($baseline * 0.95)) {
            return [
                'trigger_key' => 'REC_PRODUCTIVE_TIME_REBOUND',
                'title' => "🌈 Great Rebound: {$user->name}",
                'message' => "{$user->name} bounced back to full productivity after a difficult dip last week. High-impact moment to encourage their turnaround!",
                'suggested_preset' => 'GREAT_WORK',
            ];
        }

        return null;
    }

    /**
     * Trigger 13: Attendance recovers after a rough patch (1 holiday allowed)
     */
    public function checkAttendanceRecovery(User $user, Carbon $weekStart): ?array
    {
        $currAtt = Helper::getUserAttendance($user->id, $weekStart->copy(), $weekStart->copy()->endOfWeek());
        $prevAtt = Helper::getUserAttendance($user->id, $weekStart->copy()->subWeek(), $weekStart->copy()->subWeek()->endOfWeek());

        // Prior week was low attendance (<= 3 days)
        if (($prevAtt['days_present'] ?? 0) <= 3 && ($currAtt['days_present'] ?? 0) >= 4) {
            return [
                'trigger_key' => 'REC_ATTENDANCE_RECOVERY',
                'title' => "👏 Strong Recovery: {$user->name}",
                'message' => "{$user->name} turned attendance around with solid consistency this week after a rough patch. Reinforce this momentum!",
                'suggested_preset' => 'CONSISTENT_EFFORT',
            ];
        }

        return null;
    }

    /**
     * Trigger 14: First full month of consistent tracked hours since joining
     */
    public function checkFirstMonthConsistency(User $user): ?array
    {
        $daysSinceJoined = Carbon::parse($user->created_at)->diffInDays(Carbon::now());
        if ($daysSinceJoined >= 30 && $daysSinceJoined <= 65) {
            $totalMonthSec = UserProductivitySummary::where('user_id', $user->id)
                ->where('date', '>=', Carbon::now()->subDays(30)->toDateString())
                ->sum('total_seconds');

            if ($totalMonthSec >= 130 * 3600) {
                // Ensure trigger only fires once
                $alreadyFired = RecognitionPrompt::where('candidate_user_id', $user->id)
                    ->where('trigger_key', 'MLS_FIRST_MONTH_CONSISTENT')
                    ->exists();

                if (!$alreadyFired) {
                    return [
                        'trigger_key' => 'MLS_FIRST_MONTH_CONSISTENT',
                        'title' => "🎉 First Month Milestone: {$user->name}",
                        'message' => "Congratulations to {$user->name} on completing their first full month of solid tracking!",
                        'suggested_preset' => 'GREAT_WORK',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Trigger 15: Work anniversary combined with hours milestone
     */
    public function checkWorkAnniversaryAndMilestone(User $user): ?array
    {
        $createdAt = Carbon::parse($user->created_at);
        $years = $createdAt->diffInYears(Carbon::now());

        if ($years >= 1 && $createdAt->format('m-d') === Carbon::now()->format('m-d')) {
            $totalSecondsAllTime = UserProductivitySummary::where('user_id', $user->id)->sum('total_seconds');
            $hours = round($totalSecondsAllTime / 3600);

            if ($hours >= 500) {
                return [
                    'trigger_key' => 'MLS_WORK_ANNIVERSARY_HOURS',
                    'title' => "🎂 Work Anniversary: {$user->name}",
                    'message' => "Happy {$years}-year work anniversary to {$user->name}! Over {$hours} hours tracked with the team.",
                    'suggested_preset' => 'ABOVE_AND_BEYOND',
                ];
            }
        }

        return null;
    }

    /**
     * Trigger 16: Most improved week-over-week % increase in productive hours
     */
    public function checkMostImprovedProductiveHours(array $teamUserIds, Carbon $weekStart): ?array
    {
        $maxGainPct = 0;
        $bestUserId = null;

        foreach ($teamUserIds as $uId) {
            $currProd = UserProductivitySummary::where('user_id', $uId)
                ->whereBetween('date', [$weekStart->toDateString(), $weekStart->copy()->endOfWeek()->toDateString()])
                ->sum('productive_seconds');

            $prevProd = UserProductivitySummary::where('user_id', $uId)
                ->whereBetween('date', [$weekStart->copy()->subWeek()->toDateString(), $weekStart->copy()->subWeek()->endOfWeek()->toDateString()])
                ->sum('productive_seconds');

            if ($prevProd > 12 * 3600 && $currProd > 20 * 3600) {
                $gain = ($currProd - $prevProd) / $prevProd;
                if ($gain > $maxGainPct && $gain >= 0.25) {
                    $maxGainPct = $gain;
                    $bestUserId = $uId;
                }
            }
        }

        if ($bestUserId && $maxGainPct >= 0.25) {
            $pct = round($maxGainPct * 100);
            return [
                'candidate_id' => $bestUserId,
                'message' => "productive output jumped {$pct}% week-over-week — Most Improved across the team!",
            ];
        }

        return null;
    }

    /**
     * Trigger 17: Closing the gap — someone below team average moves within range
     */
    public function checkClosingTheGap(User $user, array $teamUserIds, Carbon $weekStart): ?array
    {
        if (count($teamUserIds) < 3) {
            return null;
        }

        $w0Team = UserProductivitySummary::whereIn('user_id', $teamUserIds)
            ->whereBetween('date', [$weekStart->toDateString(), $weekStart->copy()->endOfWeek()->toDateString()])
            ->sum('productive_seconds') / count($teamUserIds);

        $w1Team = UserProductivitySummary::whereIn('user_id', $teamUserIds)
            ->whereBetween('date', [$weekStart->copy()->subWeek()->toDateString(), $weekStart->copy()->subWeek()->endOfWeek()->toDateString()])
            ->sum('productive_seconds') / count($teamUserIds);

        $w0User = UserProductivitySummary::where('user_id', $user->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekStart->copy()->endOfWeek()->toDateString()])
            ->sum('productive_seconds');

        $w1User = UserProductivitySummary::where('user_id', $user->id)
            ->whereBetween('date', [$weekStart->copy()->subWeek()->toDateString(), $weekStart->copy()->subWeek()->endOfWeek()->toDateString()])
            ->sum('productive_seconds');

        if ($w1User < ($w1Team * 0.70) && $w0User >= ($w0Team * 0.90) && $w0User >= 20 * 3600) {
            return [
                'trigger_key' => 'REL_CLOSING_THE_GAP',
                'title' => "📈 Closing the Gap: {$user->name}",
                'message' => "{$user->name} closed the gap to the team average this week — commendable progress showing in the data.",
                'suggested_preset' => 'CONSISTENT_EFFORT',
            ];
        }

        return null;
    }

    /**
     * Dispatch In-App Notification and Email to Manager / Admin
     */
    public function dispatchPromptNotification(
        User $admin,
        User $candidate,
        string $triggerKey,
        string $title,
        string $message,
        string $suggestedPreset
    ): void {
        try {
            // 1. Record prompt in DB
            $prompt = RecognitionPrompt::create([
                'admin_id' => $admin->id,
                'candidate_user_id' => $candidate->id,
                'trigger_key' => $triggerKey,
                'title' => $title,
                'message' => $message,
                'suggested_preset' => $suggestedPreset,
                'status' => 'PENDING',
                'sent_at' => Carbon::now(),
            ]);

            // 2. In-App Notification
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'recognition_prompt',
                'title' => $title,
                'message' => $message,
                'triggered_by' => $candidate->id,
                'related_type' => 'user',
                'related_id' => $candidate->id,
                'is_read' => false,
            ]);

            // 3. Email Notification to Manager
            if (!empty($admin->email)) {
                $emailBody = view('email.recognition_prompt_email', [
                    'managerName' => $admin->name,
                    'candidateName' => $candidate->name,
                    'title' => $title,
                    'promptMessage' => $message,
                    'actionUrl' => config('app.url') . '/dashboard?recognize_user_id=' . $candidate->id,
                ])->render();

                Helper::sendEmail(
                    $admin->email,
                    $title,
                    $emailBody,
                    $admin->name
                );
            }

            Log::info("Recognition prompt dispatched successfully for candidate {$candidate->id} to admin {$admin->id} ({$triggerKey})");
        } catch (\Exception $e) {
            Log::error("Failed to dispatch recognition prompt: " . $e->getMessage());
        }
    }
}
