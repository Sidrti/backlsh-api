<?php

namespace App\Filament\Widgets;

use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Widgets\Widget;
use Carbon\Carbon;

class DashboardFilter extends Widget implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static string $view = 'filament.widgets.dashboard-filter';

    protected static ?int $sort = -3;

    public $startDate;
    public $endDate;

    public function mount(): void
    {
        $this->startDate = Carbon::now()->subDays(30)->format('Y-m-d');
        $this->endDate = Carbon::now()->format('Y-m-d');

        $this->form->fill([
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Card::make()
                ->schema([
                    Forms\Components\Grid::make(2)
                        ->schema([
                            DatePicker::make('startDate')
                                ->label('Start Date')
                                ->default(now()->subDays(30))
                                ->reactive()
                                ->afterStateUpdated(function ($state) {
                                    $this->startDate = $state;
                                    $this->emitFilterUpdated();
                                }),
                            DatePicker::make('endDate')
                                ->label('End Date')
                                ->default(now())
                                ->reactive()
                                ->afterStateUpdated(function ($state) {
                                    $this->endDate = $state;
                                    $this->emitFilterUpdated();
                                }),
                        ])
                ])
        ];
    }

    protected function emitFilterUpdated()
    {
        $this->emit('dashboardFilterUpdated', [
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);
    }
}
