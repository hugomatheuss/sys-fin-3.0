<?php

namespace App\Filament\Widgets;

use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

abstract class GraficoFinanceiro extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected static ?string $pollingInterval = null;

    protected static ?string $maxHeight = '320px';

    protected static string $view = 'filament.widgets.grafico-financeiro';

    protected string $eixo = 'x';

    public function temDados(): bool
    {
        $dados = $this->getCachedData();

        return array_key_exists('quantidade', $dados)
            ? $dados['quantidade'] > 0
            : count($dados['labels']) > 0;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(str_replace('__EIXO__', $this->eixo, <<<'JS'
            {
                indexAxis: '__EIXO__',
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const valor = String(context.raw ?? '0.00');
                                const [inteiro, centavos = '00'] = valor.replace(/^-/, '').split('.');
                                const sinal = valor.startsWith('-') ? '-' : '';
                                return context.dataset.label + ': ' + sinal + 'R$ '
                                    + inteiro.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
                                    + ',' + centavos.padEnd(2, '0');
                            },
                        },
                    },
                },
                scales: {
                    ['__EIXO__']: {
                        ticks: {
                            callback: function (value) {
                                const label = this.getLabelForValue(value);
                                return label.length > 28 ? label.slice(0, 27) + '…' : label;
                            },
                        },
                    },
                    ['__EIXO__' === 'x' ? 'y' : 'x']: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => new Intl.NumberFormat('pt-BR', {
                                style: 'currency', currency: 'BRL', notation: 'compact', maximumFractionDigits: 1,
                            }).format(value),
                        },
                    },
                },
            }
            JS));
    }
}
