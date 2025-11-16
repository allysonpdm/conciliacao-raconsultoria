<?php

namespace App\Livewire;

use App\Models\Conciliacao;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ConciliacaoOverview extends StatsOverviewWidget
{
    public ?int $conciliacaoId = null;
    protected ?Conciliacao $conciliacao = null;

    protected function getStats(): array
    {
        // Busca a conciliação se o ID foi fornecido
        if ($this->conciliacaoId) {
            $this->conciliacao = Conciliacao::find($this->conciliacaoId);
        }

        $conciliacao = $this->conciliacao;

        if (!$conciliacao) {
            return [
                Stat::make('Empresas Conciliadas', 0)
                    ->description('Nenhum arquivo processado')
                    ->color('gray')
                    ->icon('heroicon-o-document'),
            ];
        }

        $contasCount = number_format($conciliacao->contas->count() ?? 0, 0, ',', '.');
        $pagamentosCount = number_format($conciliacao->pagamentos->count() ?? 0, 0, ',', '.');
        $notasCount = number_format($conciliacao->notas->count() ?? 0, 0, ',', '.');
        $ajustesCount = number_format($conciliacao->ajustes->count() ?? 0, 0, ',', '.');
        $errosCount = number_format($conciliacao->possiveisErrosPagamento->count() ?? 0, 0, ',', '.');

        $contasDesblanceadasCount = number_format($conciliacao->contas()->where('balanceado', false)->count() ?? 0, 0, ',', '.');
        return [
            Stat::make('Empresas Conciliadas', $contasCount)
                ->description('Total de contas processadas')
                ->color($contasCount > 0 ? 'info' : 'danger')
                ->icon($contasCount > 0 ? 'heroicon-o-check-circle' : 'heroicon-o-clock'),

            Stat::make('Pagamentos', $pagamentosCount)
                ->description('Total de pagamentos processados')
                ->color('info')
                ->icon('heroicon-o-currency-dollar'),

            Stat::make('Notas Fiscais', $notasCount)
                ->description('Total de notas fiscais processadas')
                ->color('info')
                ->icon('heroicon-o-document-text'),

            Stat::make('Ajustes', $ajustesCount)
                ->description('Total de ajustes feitos antes da conciliação')
                ->color('info')
                ->icon('heroicon-o-adjustments-horizontal'),

            Stat::make('Possiveis Erros', $errosCount)
                ->description('Total de possíveis erros encontrados')
                ->color($errosCount > 0 ? 'danger' : 'success')
                ->icon($errosCount > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle'),

            Stat::make('Contas Desbalanceadas', $contasDesblanceadasCount)
                ->description('Total de contas desbalanceadas')
                ->color($contasDesblanceadasCount > 0 ? 'danger' : 'success')
                ->icon($contasDesblanceadasCount > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle'),
        ];
    }

    private function getStatusText(Conciliacao $conciliacao): string
    {
        if (!$conciliacao->file) {
            return 'Aguardando upload';
        }

        $contasCount = $conciliacao->contas->count() ?? 0;

        if ($contasCount > 0) {
            return 'Processado com sucesso';
        }

        return 'Em processamento';
    }

    private function getStatusColor(Conciliacao $conciliacao): string
    {
        if (!$conciliacao->file) {
            return 'gray';
        }

        $contasCount = $conciliacao->contas->count() ?? 0;

        if ($contasCount > 0) {
            return 'success';
        }

        return 'warning';
    }

    private function getStatusIcon(Conciliacao $conciliacao): string
    {
        if (!$conciliacao->file) {
            return 'heroicon-o-clock';
        }

        $contasCount = $conciliacao->contas->count() ?? 0;

        if ($contasCount > 0) {
            return 'heroicon-o-check-circle';
        }

        return 'heroicon-o-cog';
    }
}
