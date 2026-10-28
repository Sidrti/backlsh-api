<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeZone;

class ReportTime
{
    public static function rememberBrowserTimezone(User $user, ?string $timezone): void
    {
        if (!$timezone || !in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            return;
        }

        $settings = $user->settings ?? [];
        if (!empty($settings['timezone'])) {
            return; // Keep the report timezone explicitly chosen in profile settings.
        }

        $settings['timezone'] = $timezone;
        $user->settings = $settings;
        $user->save();
    }

    public static function utcDayBounds(CarbonInterface $day): array
    {
        return [
            $day->copy()->startOfDay()->utc()->toDateTimeString(),
            $day->copy()->startOfDay()->addDay()->utc()->toDateTimeString(),
        ];
    }

    public static function format(?string $utcTime, string $timezone): string
    {
        return $utcTime ? Carbon::parse($utcTime, 'UTC')->setTimezone($timezone)->format('h:i A') : 'N/A';
    }
}
