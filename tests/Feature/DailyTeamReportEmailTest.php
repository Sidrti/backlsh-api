<?php

namespace Tests\Feature;

use App\Support\ReportTime;
use Tests\TestCase;

class DailyTeamReportEmailTest extends TestCase
{
    public function test_email_shows_local_tracker_time_and_timezone_without_claiming_a_logout(): void
    {
        $html = view('emails.daily_team_report', ['reportData' => [
            'ownerName' => 'Fred Zelhart',
            'date' => 'Oct 27, 2026',
            'timezone' => 'America/Chicago',
            'totalTeamHours' => '15m',
            'projectsData' => [],
            'employeesData' => [[
                'name' => 'Fred Zelhart',
                'total_hours' => '15m',
                'logged_in' => ReportTime::format('2026-10-27 16:01:56', 'America/Chicago'),
                'logged_out' => ReportTime::format('2026-10-27 16:33:37', 'America/Chicago'),
                'top_websites' => 'None',
                'top_apps' => 'brave (11m), outlook (1m)',
            ]],
        ]])->render();

        $this->assertStringContainsString('11:01 AM', $html);
        $this->assertStringContainsString('11:33 AM', $html);
        $this->assertStringContainsString('America/Chicago', $html);
        $this->assertStringContainsString('Tracker Login', $html);
        $this->assertStringContainsString('Last Activity', $html);
        $this->assertStringNotContainsString('04:01 PM', $html);
        $this->assertStringNotContainsString('yesterday', $html);
        $this->assertStringNotContainsString('<th>Logout</th>', $html);
    }
}
