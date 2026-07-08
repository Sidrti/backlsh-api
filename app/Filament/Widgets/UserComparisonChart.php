<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\User;
use App\Models\Subscription;
use Carbon\Carbon;

class UserComparisonChart extends ChartWidget
{
    protected static ?string $heading = 'Users Registered Vs Subscriptions Bought (Last 6 Months)';

    protected static ?int $sort = -1;

    protected function getData(): array
    {
        $months = [];
        $usersData = [];
        $subscriptionsData = [];

        // Loop through last 6 months in chronological order
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = Carbon::now()->subMonths($i);
            $months[] = $monthDate->format('M Y');

            $startOfMonth = $monthDate->copy()->startOfMonth();
            $endOfMonth = $monthDate->copy()->endOfMonth();

            // Count users registered in this month
            $usersCount = User::where('parent_user_id', 0)->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();

            // Count subscriptions bought in this month
            $subsCount = Subscription::where('stripe_status', 'ACTIVE')
                ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                ->count();

            $usersData[] = $usersCount;
            $subscriptionsData[] = $subsCount;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Users Registered',
                    'data' => $usersData,
                    'backgroundColor' => '#6366f1',
                    'borderColor' => '#6366f1',
                ],
                [
                    'label' => 'Subscriptions Bought',
                    'data' => $subscriptionsData,
                    'backgroundColor' => '#22c55e',
                    'borderColor' => '#22c55e',
                ],
            ],
            'labels' => $months,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
