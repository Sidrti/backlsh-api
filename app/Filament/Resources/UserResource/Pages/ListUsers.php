<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Helpers\Helper;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('sync_brevo')
                ->label('Sync with Brevo')
                ->icon('heroicon-o-refresh')
                ->color('success')
                ->action(function () {
                    $result = Helper::syncUsersToBrevo();
                    $this->notify('success', $result);
                })
                ->requiresConfirmation()
                ->color('success'),
            Actions\Action::make('back_to_root')
                ->label('Back to Root')
                ->url(UserResource::getUrl('index'))
                ->visible(fn () => request()->query('parent_user_id')),
        ];
    }
}
