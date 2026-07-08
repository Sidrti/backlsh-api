<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use App\Models\User;
use App\Models\Subscription;
use App\Models\UserActivity;
use Carbon\Carbon;

class DashboardStats extends BaseWidget
{
    protected static ?int $sort = -2;

    public $startDate;
    public $endDate;

    protected $listeners = ['dashboardFilterUpdated' => 'updateFilter'];

    public function mount(): void
    {
        $this->startDate = Carbon::now()->subDays(30)->format('Y-m-d');
        $this->endDate = Carbon::now()->format('Y-m-d');
    }

    public function updateFilter(array $filter): void
    {
        $this->startDate = $filter['startDate'];
        $this->endDate = $filter['endDate'];
    }

    protected function getCards(): array
    {
        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        // 1. Users Registered
        $usersCount = User::where('parent_user_id', 0)->whereBetween('created_at', [$start, $end])->count();

        // 2. Subscriptions Bought
        $subscriptionsCount = Subscription::where('stripe_status', 'ACTIVE')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        // 3. Revenue Earned
        $subscriptions = Subscription::where('stripe_status', 'ACTIVE')
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $revenue = 0.0;
        $unitPrice = (float) config('app.unit_price', 2.99);

        foreach ($subscriptions as $sub) {
            $price = $sub->stripe_price;
            if (is_numeric($price)) {
                $revenue += (float) $price;
            } else {
                $revenue += $unitPrice * ($sub->quantity ?? 1);
            }
        }

        // 4. Currently Active (Last 7 Days)
        $sevenDaysAgo = Carbon::now()->subDays(7);
        $activeUserIds = UserActivity::where('start_datetime', '>=', $sevenDaysAgo)
            ->distinct()
            ->pluck('user_id');

        $activeAdmins = User::whereIn('id', $activeUserIds)->where('parent_user_id', 0)->count();
        $activeMembers = User::whereIn('id', $activeUserIds)->where('parent_user_id', '>', 0)->count();
        $totalActive = $activeAdmins + $activeMembers;

        return [
            Card::make('Users Registered', number_format($usersCount))
                ->description('Total new users registered')
                ->descriptionIcon('heroicon-o-user-add')
                ->color('success'),
            Card::make('Subscriptions Bought', number_format($subscriptionsCount))
                ->description('Total active subscriptions bought')
                ->descriptionIcon('heroicon-o-credit-card')
                ->color('primary'),
            Card::make('Revenue Earned', '$' . number_format($revenue, 2))
                ->description('Total subscription revenue')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('success'),
            Card::make('Currently Active (Last 7 Days)', number_format($totalActive))
                ->description("{$activeAdmins} admins | {$activeMembers} members")
                ->descriptionIcon('heroicon-o-users')
                ->color('success'),
        ];
    }
}
