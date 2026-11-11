<?php

namespace App\Filament\Widgets;

use App\Support\PainelFinanceiro;
use App\Support\ValorMonetario;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $dados = app(PainelFinanceiro::class)->resumo($this->filters['periodo'] ?? null);
        $movimentacoes = $dados['movimentacoes'];

        return [
            Stat::make('Contas vencidas', ValorMonetario::formatar($dados['vencidas']['valor']))
                ->description($this->contagemContas($dados['vencidas']['quantidade']).' em atraso')
                ->color($dados['vencidas']['quantidade'] ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle')->extraAttributes(['style' => 'overflow-wrap: anywhere;']),
            Stat::make('Vencem em 7 dias', ValorMonetario::formatar($dados['proximas']['valor']))
                ->description($this->contagemContas($dados['proximas']['quantidade']).' de '.now()->format('d/m').' a '.now()->addDays(6)->format('d/m'))
                ->color('warning')->icon('heroicon-o-calendar-days')->extraAttributes(['style' => 'overflow-wrap: anywhere;']),
            Stat::make('Contas pagas no período', ValorMonetario::formatar($dados['pagas']['valor']))
                ->description($this->contagemContas($dados['pagas']['quantidade']).' pela data de pagamento')
                ->color('success')->icon('heroicon-o-check-circle')->extraAttributes(['style' => 'overflow-wrap: anywhere;']),
            Stat::make('Recebimentos no período', ValorMonetario::formatar($movimentacoes['recebimento']))
                ->description($movimentacoes['quantidade'].($movimentacoes['quantidade'] === 1 ? ' lançamento' : ' lançamentos').' no período')
                ->color('success')->icon('heroicon-o-arrow-down-left')->extraAttributes(['style' => 'overflow-wrap: anywhere;']),
            Stat::make('Pagamentos no período', ValorMonetario::formatar($movimentacoes['pagamento']))
                ->description('Pagamentos registrados nos lançamentos')
                ->color('danger')->icon('heroicon-o-arrow-up-right')->extraAttributes(['style' => 'overflow-wrap: anywhere;']),
            Stat::make('Saldo dos lançamentos', ValorMonetario::formatar($dados['saldo']))
                ->description('Recebimentos menos pagamentos no período')
                ->color(bccomp($dados['saldo'], '0', 2) < 0 ? 'danger' : 'success')
                ->icon('heroicon-o-arrows-right-left')->extraAttributes(['style' => 'overflow-wrap: anywhere;']),
        ];
    }

    private function contagemContas(int $quantidade): string
    {
        return $quantidade.($quantidade === 1 ? ' conta' : ' contas');
    }
}
