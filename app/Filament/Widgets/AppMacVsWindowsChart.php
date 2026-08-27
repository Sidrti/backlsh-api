<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\User;
use App\Models\Download;
use Carbon\Carbon;

class AppMacVsWindowsChart extends ChartWidget
{
    protected static ?string $heading = 'Mac Vs Windows';

    protected static ?int $sort = 1;

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

        // 1. Fetch downloads by platform in date range
        $macDownloads = Download::where('os', 'mac')
            ->whereBetween('created_date_time', [$start, $end])
            ->count();

        $winDownloads = Download::where('os', 'windows')
            ->whereBetween('created_date_time', [$start, $end])
            ->count();

        // 2. Fetch installs (based on activity start date) in date range
        $installs = User::getInstallsData();
        
        $macInstalls = $installs->where('platform', 'mac')
            ->whereBetween('installed_at', [$start, $end])
            ->count();

        $winInstalls = $installs->where('platform', 'windows')
            ->whereBetween('installed_at', [$start, $end])
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Downloads',
                    'data' => [$macDownloads, $winDownloads],
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#3b82f6',
                ],
                [
                    'label' => 'Installs',
                    'data' => [$macInstalls, $winInstalls],
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#10b981',
                ],
            ],
            'labels' => ['Mac', 'Windows'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
