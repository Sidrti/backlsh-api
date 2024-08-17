<?php

namespace App\Filament\Resources\UserStatsResource\Widgets;

use App\Models\User;
use Filament\Widgets\Widget;

class UserStats extends Widget
{
    protected static string $view = 'filament.widgets.user-stats';

    protected function getData(): array
    {
        return [
            'userCount' => User::count()
        ];
    }
}
