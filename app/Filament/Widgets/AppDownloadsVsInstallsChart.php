<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\User;
use App\Models\Download;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AppDownloadsVsInstallsChart extends ChartWidget
{
    protected static ?string $heading = 'Downloads Vs Installs';

    protected static ?int $sort = 0;

    public $startDate;
    public $endDate;

    protected $listeners = ['dashboardFilterUpdated' => 'updateFilter'];

    public function mount(): void
    {
        $this->startDate = Carbon::now()->subDays(30)->format('Y-m-d');
        $this->endDate = Carbon::now()->format('Y-m-d');

        parent::mount();
    }

    public function updateFilter(array $filter): void
    {
        $this->startDate = $filter['startDate'];
        $this->endDate = $filter['endDate'];
    }

    protected function getData(): array
    {
        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        $labels = [];
        $downloadsData = [];
        $installsData = [];

        $diffInDays = $start->diffInDays($end);

        // Fetch all downloads within the period
        $downloads = Download::whereBetween('created_date_time', [$start, $end])->get();

        // Fetch all installs (based on activity)
        $installs = User::getInstallsData();

        if ($diffInDays <= 35) {
            // Group by Day
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $labels[] = $date->format('d M');

                $dayStart = $date->copy()->startOfDay();
                $dayEnd = $date->copy()->endOfDay();

                $downloadsCount = $downloads->whereBetween('created_date_time', [$dayStart, $dayEnd])->count();
                $installsCount = $installs->whereBetween('installed_at', [$dayStart, $dayEnd])->count();

                $downloadsData[] = $downloadsCount;
                $installsData[] = $installsCount;
            }
        } else {
            // Group by Month
            $current = $start->copy()->startOfMonth();
            while ($current->lte($end)) {
                $labels[] = $current->format('M Y');

                $monthStart = $current->copy()->startOfMonth();
                $monthEnd = $current->copy()->endOfMonth();

                $downloadsCount = $downloads->whereBetween('created_date_time', [$monthStart, $monthEnd])->count();
                $installsCount = $installs->whereBetween('installed_at', [$monthStart, $monthEnd])->count();

                $downloadsData[] = $downloadsCount;
                $installsData[] = $installsCount;

                $current->addMonth();
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Downloads',
                    'data' => $downloadsData,
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#3b82f6',
                ],
                [
                    'label' => 'Installs',
                    'data' => $installsData,
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#10b981',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
