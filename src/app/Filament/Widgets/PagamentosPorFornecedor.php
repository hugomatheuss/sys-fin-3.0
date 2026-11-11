<?php

namespace App\Filament\Widgets;

use App\Support\PainelFinanceiro;
use Illuminate\Contracts\Support\Htmlable;

class PagamentosPorFornecedor extends GraficoFinanceiro
{
    protected static ?string $heading = 'Maiores pagamentos por fornecedor';

    protected string $eixo = 'y';

    protected function getData(): array
    {
        $fornecedores = app(PainelFinanceiro::class)->fornecedores($this->filters['periodo'] ?? null);

        return [
            'labels' => array_column($fornecedores, 'fornecedor'),
            'datasets' => [[
                'label' => 'Contas pagas', 'data' => array_column($fornecedores, 'valor'),
                'backgroundColor' => '#d97706', 'borderColor' => '#d97706',
            ]],
        ];
    }

    public function getDescription(): string|Htmlable|null
    {
        return count($this->getCachedData()['labels'])
            ? 'Os cinco maiores totais de contas pagas no mês selecionado.'
            : 'Nenhuma conta paga no período selecionado.';
    }
}
