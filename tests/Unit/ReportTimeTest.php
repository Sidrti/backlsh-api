<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\ReportTime;
use Carbon\Carbon;
use Mockery;
use PHPUnit\Framework\TestCase;

class ReportTimeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_freds_tracker_login_is_formatted_in_local_time_even_when_php_uses_another_timezone(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Asia/Kolkata');
        try {
            $this->assertSame('11:01 AM', ReportTime::format('2026-10-27 16:01:56', 'America/Chicago'));
            $this->assertSame('11:33 AM', ReportTime::format('2026-10-27 16:33:37', 'America/Chicago'));
            $this->assertSame('N/A', ReportTime::format(null, 'America/Chicago'));
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function test_local_day_includes_the_next_utc_date_for_western_timezones(): void
    {
        $this->assertSame(['2026-10-27 05:00:00', '2026-10-28 05:00:00'],
            ReportTime::utcDayBounds(Carbon::parse('2026-10-27', 'America/Chicago')));
    }

    public function test_local_day_includes_the_previous_utc_date_for_eastern_timezones(): void
    {
        $this->assertSame(['2026-10-26 18:30:00', '2026-10-27 18:30:00'],
            ReportTime::utcDayBounds(Carbon::parse('2026-10-27', 'Asia/Kolkata')));
    }

    public function test_day_boundaries_and_display_follow_daylight_saving_changes(): void
    {
        $this->assertSame(['2026-11-01 05:00:00', '2026-11-02 06:00:00'],
            ReportTime::utcDayBounds(Carbon::parse('2026-11-01', 'America/Chicago')));
        $this->assertSame('10:01 AM', ReportTime::format('2026-11-02 16:01:56', 'America/Chicago'));
    }

    public function test_browser_timezone_is_saved_without_losing_existing_settings(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->settings = ['report_time' => '18:00'];
        $user->shouldReceive('save')->once()->andReturn(true);
        ReportTime::rememberBrowserTimezone($user, 'America/Chicago');
        $this->assertSame(['report_time' => '18:00', 'timezone' => 'America/Chicago'], $user->settings);
    }

    public function test_browser_timezone_does_not_override_a_selected_report_timezone(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->settings = ['timezone' => 'Europe/London'];
        $user->shouldNotReceive('save');
        ReportTime::rememberBrowserTimezone($user, 'America/Chicago');
        $this->assertSame('Europe/London', $user->settings['timezone']);
    }

    public function test_invalid_or_missing_browser_timezone_is_not_saved(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->settings = null;
        $user->shouldNotReceive('save');
        ReportTime::rememberBrowserTimezone($user, 'Invalid/Timezone');
        ReportTime::rememberBrowserTimezone($user, null);
        $this->assertNull($user->settings);
    }
}
