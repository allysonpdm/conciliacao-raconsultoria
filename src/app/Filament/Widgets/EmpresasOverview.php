<?php

namespace App\Filament\Widgets;

use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class EmpresasOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    protected function getStats(): array
    {
        $total = Auth::user()->empresas()->count();

        return [
            Stat::make('Empresas cadastradas', number_format($total))
                ->description('Total cadastradas')
                ->descriptionIcon(Heroicon::BuildingOffice)
                ->color('success')
                ->icon(Heroicon::ChartBar)
                //->chart([1, 4, 7, 4, 6, 8]) // gráfico mini decorativo
                ->extraAttributes([
                    'class' =>
                        'rounded-xl bg-gradient-to-br from-primary-600 via-primary-500 to-primary-700 ' .
                        'text-white shadow-lg border border-primary-300 transform hover:scale-[1.01] transition',
                ])
                ->url(fn () => route('filament.conciliacao.resources.empresas.index'), true),
        ];
    }

    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'sm' => 2,
            'md' => 3,
            'xl' => 4,
            '2xl' => 5,
            'lg' => 6,
        ];
    }

    public static function canView(): bool
    {
        return true;
    }
}
