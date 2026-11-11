<?php

namespace App\Filament\Widgets;

use App\Models\Conta;
use App\Models\Lancamento;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $inicioDoMes = now()->startOfMonth();
        $periodo = [$inicioDoMes->toDateString(), $inicioDoMes->copy()->endOfMonth()->toDateString()];

        return [
            Stat::make('Contas', Conta::query()->where('user_id', '=', auth()->id())->count())
                ->description('Todas as suas contas cadastradas.')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Contas não pagas', Conta::query()
                ->where('status', '=', '2')
                ->where('user_id', '=', auth()->id())
                ->whereBetween('dataVencimento', $periodo)
                ->count()
            )
                ->description('Contas pendentes com vencimento neste mês.'),
            Stat::make('Contas pagas', Conta::query()
                ->where('status', '=', '1')
                ->where('user_id', '=', auth()->id())
                ->whereBetween('dataPagamento', $periodo)
                ->count()
            )
                ->description('Contas com pagamento neste mês.'),
            Stat::make('Lançamentos', Lancamento::query()->where('user_id', '=', auth()->id())->count())
                ->description('Todos os seus lançamentos cadastrados.')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Dinheiro', Lancamento::query()
                ->where('tipoRecebimento', '=', '1')
                ->where('user_id', '=', auth()->id())
                ->whereBetween('dataLancamento', $periodo)
                ->count()
            )
                ->description('Recebimentos em dinheiro lançados neste mês.'),
            Stat::make('Bancário', Lancamento::query()
                ->where('tipoRecebimento', '=', '0')
                ->where('user_id', '=', auth()->id())
                ->whereBetween('dataLancamento', $periodo)
                ->count()
            )
                ->description('Recebimentos bancários lançados neste mês.'),
            Stat::make('Mercadoria', Lancamento::query()
                ->where('tipoPagamento', '=', '1')
                ->where('user_id', '=', auth()->id())
                ->whereBetween('dataLancamento', $periodo)
                ->count()
            )
                ->description('Pagamentos em mercadoria lançados neste mês.'),
            Stat::make('Outros', Lancamento::query()
                ->where('tipoPagamento', '=', '0')
                ->where('user_id', '=', auth()->id())
                ->whereBetween('dataLancamento', $periodo)
                ->count()
            )
                ->description('Outros pagamentos lançados neste mês.'),
        ];
    }
}
