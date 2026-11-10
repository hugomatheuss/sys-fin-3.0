<?php

namespace Tests\Feature;

use App\Filament\Resources\ContaResource\Pages\CreateConta;
use App\Filament\Resources\ContaResource\Pages\EditConta;
use App\Filament\Resources\LancamentoResource\Pages\CreateLancamento;
use App\Filament\Resources\LancamentoResource\Pages\EditLancamento;
use App\Models\Conta;
use App\Models\Lancamento;
use App\Models\User;
use Database\Seeders\PortfolioDemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FormularioFinanceiroTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Execute com phpunit.portfolio.xml: os testes exigem SQLite em memória.');
        }

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->seed(PortfolioDemoSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::firstOrFail());
    }

    public function test_cadastro_de_conta_preserva_valor_alto_e_ignora_usuario_forjado(): void
    {
        $other = User::where('id', '!=', auth()->id())->firstOrFail();
        Livewire::test(CreateConta::class)->fillForm([
            'fornecedor' => 'Fornecedor fictício', 'numeroDocumento' => 'FORM-01',
            'valor' => '90.071.992.547.409,93', 'tipo' => '1', 'status' => '2',
            'dataVencimento' => now()->toDateString(),
        ])->set('data.user_id', $other->id)->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('contas', ['numeroDocumento' => 'FORM-01', 'valor' => '90071992547409.93', 'user_id' => auth()->id()]);
    }

    public function test_cadastro_de_lancamento_preserva_centavos_e_usuario(): void
    {
        Livewire::test(CreateLancamento::class)->fillForm([
            'recebimento' => '1.234,56', 'pagamento' => '0,10',
            'tipoRecebimento' => '0', 'tipoPagamento' => '1', 'dataLancamento' => now()->toDateString(),
        ])->set('data.user_id', User::where('id', '!=', auth()->id())->value('id'))
            ->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('lancamentos', ['recebimento' => '1234.56', 'pagamento' => '0.10', 'user_id' => auth()->id()]);
    }

    public function test_edicao_nao_transfere_conta_ou_lancamento_para_outro_usuario(): void
    {
        foreach (User::all() as $user) {
            $this->app['session']->flush();
            $this->actingAs($user);
            $other = User::where('id', '!=', $user->id)->firstOrFail();
            foreach ([Conta::class => EditConta::class, Lancamento::class => EditLancamento::class] as $model => $page) {
                $record = $model::where('user_id', $user->id)->firstOrFail();
                Livewire::test($page, ['record' => $record->getRouteKey()])
                    ->set('data.user_id', $other->id)->call('save')->assertHasNoFormErrors();
                $this->assertSame($user->id, $record->fresh()->user_id);
            }
        }
    }

    public function test_abrir_e_salvar_edicao_preserva_centavos_sem_float(): void
    {
        $conta = Conta::where('user_id', auth()->id())->firstOrFail();
        $conta->update(['valor' => '90071992547409.93']);
        Livewire::test(EditConta::class, ['record' => $conta->getRouteKey()])->call('save')->assertHasNoFormErrors();
        $this->assertSame('90071992547409.93', $conta->fresh()->valor);

        $lancamento = Lancamento::where('user_id', auth()->id())->firstOrFail();
        $lancamento->update(['recebimento' => '90071992547409.93', 'pagamento' => '0.10']);
        Livewire::test(EditLancamento::class, ['record' => $lancamento->getRouteKey()])->call('save')->assertHasNoFormErrors();
        $this->assertSame('90071992547409.93', $lancamento->fresh()->recebimento);
        $this->assertSame('0.10', $lancamento->fresh()->pagamento);
    }

    public function test_formularios_recusam_dinheiro_invalido_sem_gravar(): void
    {
        foreach (['abc', '1,234', '', '--1'] as $value) {
            Livewire::test(CreateConta::class)->fillForm([
                'fornecedor' => 'Fornecedor fictício', 'valor' => $value, 'tipo' => '1',
                'status' => '2', 'dataVencimento' => now()->toDateString(),
            ])->call('create')->assertHasFormErrors(['valor']);
            Livewire::test(CreateLancamento::class)->fillForm([
                'recebimento' => $value, 'pagamento' => '0,00',
                'tipoRecebimento' => '1', 'tipoPagamento' => '0', 'dataLancamento' => now()->toDateString(),
            ])->call('create')->assertHasFormErrors(['recebimento']);
        }
        $this->assertDatabaseCount('contas', 12);
        $this->assertDatabaseCount('lancamentos', 6);
    }

    public function test_validacao_no_servidor_recusa_array_no_valor(): void
    {
        $page = Livewire::test(CreateConta::class)->fillForm([
            'fornecedor' => 'Fornecedor fictício', 'valor' => '10,00', 'tipo' => '1',
            'status' => '2', 'dataVencimento' => now()->toDateString(),
        ])->instance();
        $page->data['valor'] = ['valor' => '10,00'];
        $this->expectException(ValidationException::class);
        $page->form->getState();
    }

    public function test_campos_obrigatorios_e_codigos_sao_validados_no_servidor(): void
    {
        Livewire::test(CreateConta::class)->fillForm([
            'fornecedor' => '', 'valor' => '10,00', 'tipo' => '9',
            'status' => '9', 'dataVencimento' => null,
        ])->call('create')->assertHasFormErrors(['fornecedor', 'tipo', 'status', 'dataVencimento']);
        Livewire::test(CreateLancamento::class)->fillForm([
            'recebimento' => '0,00', 'pagamento' => 'abc',
            'tipoRecebimento' => '9', 'tipoPagamento' => '9', 'dataLancamento' => null,
        ])->call('create')->assertHasFormErrors(['pagamento', 'tipoRecebimento', 'tipoPagamento', 'dataLancamento']);
        $this->assertDatabaseCount('contas', 12);
        $this->assertDatabaseCount('lancamentos', 6);
    }

    public function test_edicao_por_formulario_limpa_data_ao_reabrir_conta(): void
    {
        $conta = Conta::where('user_id', auth()->id())->where('status', '1')->firstOrFail();
        Livewire::test(EditConta::class, ['record' => $conta->getRouteKey()])
            ->fillForm(['status' => '1', 'dataPagamento' => null])->call('save')->assertHasFormErrors(['dataPagamento']);
        Livewire::test(EditConta::class, ['record' => $conta->getRouteKey()])
            ->fillForm(['status' => '2'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('2', $conta->fresh()->status);
        $this->assertNull($conta->fresh()->dataPagamento);
    }

    public function test_mass_assignment_permanece_protegido(): void
    {
        $this->assertFalse(Model::isUnguarded());
        $this->assertFalse((new Conta)->isFillable('user_id'));
        $this->assertFalse((new Lancamento)->isFillable('user_id'));
    }
}
