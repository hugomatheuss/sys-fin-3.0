<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\ContaResource;
use App\Filament\Widgets\ContasAtencao;
use App\Filament\Widgets\MovimentacaoMensal;
use App\Filament\Widgets\PagamentosPorFornecedor;
use App\Filament\Widgets\StatsOverview;
use App\Models\Conta;
use App\Models\Lancamento;
use App\Models\User;
use App\Support\PainelFinanceiro;
use Database\Seeders\PortfolioDemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardFinanceiroTest extends TestCase
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
        Conta::query()->update(['status' => '2', 'dataVencimento' => '2050-01-01', 'dataPagamento' => null]);
        Lancamento::query()->update(['dataLancamento' => '2050-01-01']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::firstOrFail());
    }

    public function test_resumo_soma_centavos_exatos_e_nao_mistura_contas_com_lancamentos(): void
    {
        foreach (User::all() as $user) {
            $this->actingAs($user);
            $this->conta($user, ['valor' => '90071992547409.93', 'dataVencimento' => '2026-11-14']);
            $this->conta($user, ['valor' => '0.10', 'dataVencimento' => '2026-11-14']);
            $this->conta($user, ['valor' => '500.00', 'status' => '1', 'dataPagamento' => '2026-11-01']);
            $this->lancamento($user, ['recebimento' => '90071992547409.93', 'pagamento' => '0.10']);
            $this->lancamento($user, ['recebimento' => '0.10', 'pagamento' => '0.20']);
            $dados = app(PainelFinanceiro::class)->resumo('2026-11');
            $this->assertSame(['quantidade' => 2, 'valor' => '90071992547410.03'], $dados['vencidas']);
            $this->assertSame(['quantidade' => 1, 'valor' => '500.00'], $dados['pagas']);
            $this->assertSame(['quantidade' => 2, 'recebimento' => '90071992547410.03', 'pagamento' => '0.30'], $dados['movimentacoes']);
            $this->assertSame('90071992547409.73', $dados['saldo']);
        }
    }

    public function test_urgencias_incluem_hoje_e_seis_dias_seguintes_e_independem_do_mes_selecionado(): void
    {
        $user = auth()->user();
        $vencida = $this->conta($user, ['valor' => '1.00', 'dataVencimento' => '2026-11-14']);
        $hoje = $this->conta($user, ['valor' => '2.00', 'dataVencimento' => '2026-11-15']);
        $limite = $this->conta($user, ['valor' => '3.00', 'dataVencimento' => '2026-11-21']);
        $fora = $this->conta($user, ['valor' => '4.00', 'dataVencimento' => '2026-11-22']);
        $paga = $this->conta($user, ['valor' => '5.00', 'status' => '1', 'dataVencimento' => '2026-11-14', 'dataPagamento' => '2026-11-14']);
        $other = $this->conta(User::whereKeyNot($user->id)->firstOrFail(), ['valor' => '999.99', 'dataVencimento' => '2026-11-14']);
        $dados = app(PainelFinanceiro::class)->resumo('2026-10');
        $this->assertSame(['quantidade' => 1, 'valor' => '1.00'], $dados['vencidas']);
        $this->assertSame(['quantidade' => 2, 'valor' => '5.00'], $dados['proximas']);
        $widget = Livewire::test(ContasAtencao::class)
            ->assertCanSeeTableRecords([$vencida, $hoje, $limite], inOrder: true)
            ->assertCanNotSeeTableRecords([$fora, $paga, $other]);
        $widget->filterTable('prazo', 'vencidas')->assertCanSeeTableRecords([$vencida])->assertCanNotSeeTableRecords([$hoje, $limite]);
        $widget->filterTable('prazo', 'proximas')->assertCanSeeTableRecords([$hoje, $limite])->assertCanNotSeeTableRecords([$vencida]);
        $action = $widget->instance()->getTable()->getAction('abrir')->record($hoje);
        $this->assertSame(ContaResource::getUrl('edit', ['record' => $hoje]), $action->getUrl());
        $this->get($action->getUrl())->assertOk();
        $this->get(ContaResource::getUrl('edit', ['record' => $other]))->assertNotFound();
    }

    public function test_filtro_por_mes_usa_datas_financeiras_e_saldo_pode_ser_negativo(): void
    {
        $user = auth()->user();
        $this->conta($user, ['status' => '1', 'valor' => '12.10', 'dataVencimento' => '2026-11-15', 'dataPagamento' => '2026-10-31']);
        $this->conta($user, ['status' => '1', 'valor' => '90.00', 'dataPagamento' => '2026-11-01']);
        $this->lancamento($user, ['dataLancamento' => '2026-10-01', 'recebimento' => '0.10', 'pagamento' => '0.20']);
        $this->lancamento($user, ['dataLancamento' => '2026-11-01', 'recebimento' => '99.00', 'pagamento' => '0.00']);
        $this->lancamento($user, ['dataLancamento' => null, 'recebimento' => '999.00']);
        $dados = app(PainelFinanceiro::class)->resumo('2026-10');
        $this->assertSame('12.10', $dados['pagas']['valor']);
        $this->assertSame('-0.10', $dados['saldo']);
        Livewire::test(StatsOverview::class, ['filters' => ['periodo' => '2026-10']])
            ->assertSee('R$ 12,10')->assertSee('-R$ 0,10')->assertDontSee('R$ 99,00');
    }

    public function test_evolucao_preenche_meses_vazios_e_respeita_a_virada_do_ano(): void
    {
        $this->travelTo(now()->setDate(2027, 1, 15));
        $user = auth()->user();
        $this->lancamento($user, ['dataLancamento' => '2026-07-31', 'recebimento' => '999.00']);
        $this->lancamento($user, ['dataLancamento' => '2026-08-01', 'recebimento' => '0.10', 'pagamento' => '0.20']);
        $this->lancamento($user, ['dataLancamento' => '2027-01-31', 'recebimento' => '90071992547409.93', 'pagamento' => '0.10']);
        $this->lancamento($user, ['dataLancamento' => '2027-02-01', 'recebimento' => '999.00']);
        $this->lancamento(User::whereKeyNot($user->id)->firstOrFail(), ['dataLancamento' => '2027-01-15', 'recebimento' => '999.00']);
        $meses = app(PainelFinanceiro::class)->evolucao('2027-01');
        $this->assertCount(6, $meses);
        $this->assertSame(['ago/2026', 'set/2026', 'out/2026', 'nov/2026', 'dez/2026', 'jan/2027'], array_column($meses, 'mes'));
        $this->assertSame(['0.10', '0.00', '0.00', '0.00', '0.00', '90071992547409.93'], array_column($meses, 'recebimento'));
        $this->assertSame(['0.20', '0.00', '0.00', '0.00', '0.00', '0.10'], array_column($meses, 'pagamento'));
    }

    public function test_ranking_de_fornecedores_agrupa_soma_e_ordena_sem_float(): void
    {
        foreach (User::all() as $user) {
            $this->actingAs($user);
            foreach ([['A', '90071992547409.91'], ['A', '0.01'], ['B', '90071992547409.93'], ['C', '12.00'], ['D', '10.00'], ['E', '8.00'], ['F', '2.00']] as [$fornecedor, $valor]) {
                $this->conta($user, ['fornecedor' => $fornecedor, 'valor' => $valor, 'status' => '1', 'dataPagamento' => '2026-11-30']);
            }
            $this->conta($user, ['fornecedor' => 'Pendente', 'valor' => '99999999999999.99']);
            $this->conta($user, ['fornecedor' => 'Outro mês', 'valor' => '99999999999999.99', 'status' => '1', 'dataPagamento' => '2026-10-31']);
            $ranking = app(PainelFinanceiro::class)->fornecedores('2026-11');
            $this->assertSame(['B', 'A', 'C', 'D', 'E'], array_column($ranking, 'fornecedor'));
            $this->assertSame(['90071992547409.93', '90071992547409.92', '12.00', '10.00', '8.00'], array_column($ranking, 'valor'));
        }
    }

    public function test_graficos_preservam_os_decimais_no_payload_e_somas_aceitam_formatos_legados(): void
    {
        $this->lancamento(auth()->user(), ['recebimento' => '90071992547409.93', 'pagamento' => '0.10']);
        $this->conta(auth()->user(), ['status' => '1', 'valor' => 'R$ 1.234,56', 'dataPagamento' => '2026-11-15']);
        $chart = new class extends MovimentacaoMensal
        {
            public function dados(): array
            {
                return $this->getData();
            }
        };
        $chart->filters = ['periodo' => '2026-11'];
        $this->assertSame(['0.00', '0.00', '0.00', '0.00', '0.00', '90071992547409.93'], $chart->dados()['datasets'][0]['data']);
        $this->assertSame(['0.00', '0.00', '0.00', '0.00', '0.00', '0.10'], $chart->dados()['datasets'][1]['data']);
        $this->assertSame('1234.56', app(PainelFinanceiro::class)->resumo('2026-11')['pagas']['valor']);
        $suppliers = new class extends PagamentosPorFornecedor
        {
            public function dados(): array
            {
                return $this->getData();
            }
        };
        $suppliers->filters = ['periodo' => '2026-11'];
        $this->assertSame(['1234.56'], $suppliers->dados()['datasets'][0]['data']);
    }

    public function test_painel_renderiza_e_propaga_o_periodo_aos_widgets(): void
    {
        $this->get('/admin')->assertOk()->assertSee('Visão financeira')->assertSee('Contas vencidas')->assertSee('Recebimentos × pagamentos')->assertSee('Contas que precisam de atenção');
        Livewire::test(Dashboard::class)->assertSet('filters.periodo', '2026-11')
            ->set('filters.periodo', '2026-10')->assertSet('filters.periodo', '2026-10');
        Livewire::test(MovimentacaoMensal::class, ['filters' => ['periodo' => '2026-10']])->assertSee('Nenhum lançamento nos seis meses');
        Livewire::test(PagamentosPorFornecedor::class, ['filters' => ['periodo' => '2026-10']])->assertSee('Nenhuma conta paga');
        Livewire::test(ContasAtencao::class)->assertSee('Nenhuma conta precisa de atenção');
    }

    public function test_periodo_adulterado_e_visitante_nao_expoem_dados(): void
    {
        foreach ([null, '2026-99', '2050-01', ['user_id' => 2], "2026-11' OR 1=1"] as $periodo) {
            $this->assertSame('2026-11', PainelFinanceiro::mes($periodo)->format('Y-m'));
        }
        $this->conta(auth()->user(), ['status' => '1', 'valor' => '12.00', 'dataPagamento' => '2026-11-15']);
        $this->lancamento(auth()->user(), ['recebimento' => '12.00']);
        app('auth')->forgetGuards();
        $this->assertNull(auth()->user());
        $dados = app(PainelFinanceiro::class)->resumo('2026-11');
        $this->assertSame('0.00', $dados['pagas']['valor']);
        $this->assertSame('0.00', $dados['saldo']);
        $this->assertSame([], app(PainelFinanceiro::class)->fornecedores('2026-11'));
        $this->assertSame(array_fill(0, 6, '0.00'), array_column(app(PainelFinanceiro::class)->evolucao('2026-11'), 'recebimento'));
        $this->get('/admin')->assertRedirect(route('filament.admin.auth.login'));
    }

    private function conta(User $user, array $data): Conta
    {
        $record = new Conta(array_merge([
            'fornecedor' => 'Fornecedor fictício', 'valor' => '0.00', 'tipo' => '1', 'status' => '2',
            'dataVencimento' => '2026-11-15', 'dataPagamento' => null,
        ], $data));
        $record->user()->associate($user);
        $record->save();

        return $record;
    }

    private function lancamento(User $user, array $data): Lancamento
    {
        $record = new Lancamento(array_merge([
            'recebimento' => '0.00', 'pagamento' => '0.00', 'tipoRecebimento' => '1',
            'tipoPagamento' => '1', 'dataLancamento' => '2026-11-15',
        ], $data));
        $record->user()->associate($user);
        $record->save();

        return $record;
    }
}
