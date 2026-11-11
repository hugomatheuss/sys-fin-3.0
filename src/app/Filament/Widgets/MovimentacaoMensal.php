<?php

namespace App\Filament\Widgets;

use App\Support\PainelFinanceiro;
use Illuminate\Contracts\Support\Htmlable;

class MovimentacaoMensal extends GraficoFinanceiro
{
    protected static ?string $heading = 'Recebimentos × pagamentos';

    protected function getData(): array
    {
        $meses = app(PainelFinanceiro::class)->evolucao($this->filters['periodo'] ?? null);

        return [
            'labels' => array_column($meses, 'mes'),
            'quantidade' => array_sum(array_column($meses, 'quantidade')),
            'datasets' => [
                ['label' => 'Recebimentos', 'data' => array_column($meses, 'recebimento'), 'backgroundColor' => '#16a34a', 'borderColor' => '#16a34a'],
                ['label' => 'Pagamentos', 'data' => array_column($meses, 'pagamento'), 'backgroundColor' => '#dc2626', 'borderColor' => '#dc2626'],
            ],
        ];
    }

    public function getDescription(): string|Htmlable|null
    {
        return $this->getCachedData()['quantidade']
            ? 'Seis meses até o período selecionado, pela data dos lançamentos.'
            : 'Nenhum lançamento nos seis meses até o período selecionado.';
    }
}
