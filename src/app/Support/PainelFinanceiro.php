<?php

namespace App\Support;

use App\Models\Conta;
use App\Models\Lancamento;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class PainelFinanceiro
{
    public static function periodos(): array
    {
        $mes = CarbonImmutable::instance(now())->startOfMonth();
        $periodos = [];
        for ($i = 0; $i < 36; $i++) {
            $data = $mes->subMonths($i);
            $periodos[$data->format('Y-m')] = ucfirst($data->locale('pt_BR')->translatedFormat('F / Y'));
        }

        return $periodos;
    }

    public static function mes(mixed $periodo = null): CarbonImmutable
    {
        if (! is_string($periodo) || ! array_key_exists($periodo, self::periodos())) {
            return CarbonImmutable::instance(now())->startOfMonth();
        }

        return CarbonImmutable::parse($periodo.'-01')->startOfDay();
    }

    public function contasVencidas(): Builder
    {
        return Conta::query()->where('user_id', auth()->id())->where('status', '2')
            ->where('dataVencimento', '<', now()->toDateString());
    }

    public function proximasContas(): Builder
    {
        return Conta::query()->where('user_id', auth()->id())->where('status', '2')
            ->where('dataVencimento', '>=', now()->toDateString())
            ->where('dataVencimento', '<', now()->addDays(7)->toDateString());
    }

    public function contasPagas(mixed $periodo = null): Builder
    {
        $mes = self::mes($periodo);

        return Conta::query()->where('user_id', auth()->id())->where('status', '1')
            ->whereBetween('dataPagamento', [$mes->toDateString(), $mes->endOfMonth()->toDateString()]);
    }

    public function resumo(mixed $periodo = null): array
    {
        $mes = self::mes($periodo);
        $movimentacoes = $this->somar(
            Lancamento::query()->where('user_id', auth()->id())
                ->whereBetween('dataLancamento', [$mes->toDateString(), $mes->endOfMonth()->toDateString()]),
            ['recebimento', 'pagamento'],
        );

        return [
            'vencidas' => $this->somar($this->contasVencidas(), ['valor']),
            'proximas' => $this->somar($this->proximasContas(), ['valor']),
            'pagas' => $this->somar($this->contasPagas($periodo), ['valor']),
            'movimentacoes' => $movimentacoes,
            'saldo' => bcsub($movimentacoes['recebimento'], $movimentacoes['pagamento'], 2),
        ];
    }

    public function evolucao(mixed $periodo = null): array
    {
        $mes = self::mes($periodo);
        $inicio = $mes->subMonths(5);
        $meses = [];
        for ($i = 0; $i < 6; $i++) {
            $data = $inicio->addMonths($i);
            $meses[$data->format('Y-m')] = [
                'mes' => $data->locale('pt_BR')->translatedFormat('M/Y'),
                'recebimento' => '0.00', 'pagamento' => '0.00', 'quantidade' => 0,
            ];
        }

        $records = Lancamento::query()->where('user_id', auth()->id())
            ->whereBetween('dataLancamento', [$inicio->toDateString(), $mes->endOfMonth()->toDateString()])
            ->select(['dataLancamento', 'recebimento', 'pagamento'])->cursor();
        foreach ($records as $record) {
            $chave = substr($record->dataLancamento, 0, 7);
            $meses[$chave]['recebimento'] = bcadd($meses[$chave]['recebimento'], $this->decimal($record->recebimento), 2);
            $meses[$chave]['pagamento'] = bcadd($meses[$chave]['pagamento'], $this->decimal($record->pagamento), 2);
            $meses[$chave]['quantidade']++;
        }

        return array_values($meses);
    }

    public function fornecedores(mixed $periodo = null): array
    {
        $fornecedores = [];
        foreach ($this->contasPagas($periodo)->select(['fornecedor', 'valor'])->cursor() as $record) {
            $chave = $record->fornecedor;
            $fornecedores[$chave] ??= ['fornecedor' => $chave, 'valor' => '0.00'];
            $fornecedores[$chave]['valor'] = bcadd($fornecedores[$chave]['valor'], $this->decimal($record->valor), 2);
        }
        usort($fornecedores, fn (array $a, array $b): int => bccomp($b['valor'], $a['valor'], 2) ?: strcmp($a['fornecedor'], $b['fornecedor']));

        return array_slice($fornecedores, 0, 5);
    }

    private function somar(Builder $query, array $campos): array
    {
        $totais = ['quantidade' => 0, ...array_fill_keys($campos, '0.00')];
        foreach ($query->select($campos)->cursor() as $record) {
            foreach ($campos as $campo) {
                $totais[$campo] = bcadd($totais[$campo], $this->decimal($record->{$campo}), 2);
            }
            $totais['quantidade']++;
        }

        return $totais;
    }

    private function decimal(string $valor): string
    {
        return ValorMonetario::normalizar($valor)
            ?? throw new InvalidArgumentException('Há valores fora do formato decimal suportado nos registros do painel.');
    }
}
