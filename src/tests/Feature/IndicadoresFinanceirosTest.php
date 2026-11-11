<?php

namespace Tests\Feature;

use App\Filament\Widgets\StatsOverview;
use App\Models\Conta;
use App\Models\Lancamento;
use App\Models\User;
use Database\Seeders\PortfolioDemoSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IndicadoresFinanceirosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Execute com phpunit.portfolio.xml: os testes exigem SQLite em memória.');
        }

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->seed(PortfolioDemoSeeder::class);
        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(12, 0));
    }

    public function test_contas_do_mes_usam_vencimento_e_pagamento_com_isolamento(): void
    {
        foreach (User::all() as $user) {
            $this->actingAs($user);
            $contas = Conta::where('user_id', $user->id)->orderBy('id')->get();
            foreach ($contas as $conta) {
                $conta->forceFill(['created_at' => '2026-11-15', 'status' => '2', 'dataVencimento' => '2026-12-01', 'dataPagamento' => null])->save();
            }
            $contas[0]->forceFill(['created_at' => '2026-10-01', 'dataVencimento' => '2026-11-01'])->save();
            $contas[1]->forceFill(['created_at' => '2026-10-01', 'dataVencimento' => '2026-11-30'])->save();
            $contas[2]->fill(['status' => '1', 'dataVencimento' => '2026-10-01', 'dataPagamento' => '2026-11-01'])->save();
            $contas[3]->fill(['status' => '1', 'dataVencimento' => '2026-12-01', 'dataPagamento' => '2026-11-30'])->save();
            $contas[4]->fill(['status' => '1', 'dataPagamento' => '2026-12-01'])->save();
            $contas[5]->fill(['status' => '1', 'dataPagamento' => '2026-10-31'])->save();
            Conta::where('user_id', '!=', $user->id)->update(['status' => '2', 'dataVencimento' => '2026-11-15']);
            $stats = $this->stats();
            $this->assertSame('R$ 1.250,50', $stats['Contas vencidas']);
            $this->assertSame('R$ 0,00', $stats['Vencem em 7 dias']);
            $this->assertSame('R$ 1.055,25', $stats['Contas pagas no período']);
        }
    }

    public function test_movimentacoes_do_mes_usam_data_de_lancamento_e_nao_de_cadastro(): void
    {
        foreach (User::all() as $user) {
            $this->actingAs($user);
            $records = Lancamento::where('user_id', $user->id)->orderBy('id')->get();
            foreach ($records as $record) {
                $record->forceFill(['created_at' => '2026-10-01', 'dataLancamento' => '2026-11-01', 'tipoRecebimento' => '1', 'tipoPagamento' => '1'])->save();
            }
            $records[1]->fill(['dataLancamento' => '2026-11-30', 'tipoRecebimento' => '0', 'tipoPagamento' => '0'])->save();
            $records[2]->forceFill(['created_at' => '2026-11-15', 'dataLancamento' => '2026-12-01'])->save();
            Lancamento::where('user_id', '!=', $user->id)->update(['dataLancamento' => '2026-11-15']);
            $stats = $this->stats();
            $this->assertSame('R$ 3.750,50', $stats['Recebimentos no período']);
            $this->assertSame('R$ 815,25', $stats['Pagamentos no período']);
            $this->assertSame('R$ 2.935,25', $stats['Saldo dos lançamentos']);
            $records[0]->update(['dataLancamento' => '2026-10-31']);
            $records[1]->update(['dataLancamento' => null]);
            $stats = $this->stats();
            foreach (['Recebimentos no período', 'Pagamentos no período', 'Saldo dos lançamentos'] as $label) {
                $this->assertSame('R$ 0,00', $stats[$label]);
            }
        }
    }

    public function test_indicadores_sem_sessao_nao_expoem_dados(): void
    {
        app('auth')->forgetGuards();
        $this->assertNull(auth()->user());
        foreach ($this->stats() as $value) {
            $this->assertSame('R$ 0,00', $value);
        }
    }

    private function stats(): array
    {
        $widget = new class extends StatsOverview
        {
            public function valores(): array
            {
                return collect($this->getStats())->mapWithKeys(fn ($stat): array => [$stat->getLabel() => $stat->getValue()])->all();
            }
        };

        return $widget->valores();
    }
}
