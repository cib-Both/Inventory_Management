<?php

namespace App\Filament\Widgets;

use App\Models\Inventory;
use App\Models\Purchase;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '10s';
    
    protected function getStats(): array
    {
        return [
            Stat::make('Total User', User::count())
                ->description('Users in This System')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),
            Stat::make('Total Inventory', Inventory::count())
                ->description('Inventory in This System')
                ->descriptionIcon('heroicon-m-inbox-stack')
                ->color('success'),
            Stat::make('Total Purchase', Purchase::count())
                ->description('All Purchase')
                ->descriptionIcon('heroicon-m-arrow-down-on-square-stack')
                ->color('info'),
            Stat::make('Inventory Loan', Inventory::where('status', '!=', 'available')->count())
                ->description('Inventory being loaned')
                ->descriptionIcon('heroicon-m-arrow-up-on-square-stack')
                ->color('warning'),
        ];
    }
}