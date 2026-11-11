<?php

namespace Tests\Feature;

use App\Filament\Resources\ContaResource\Pages\ListContas;
use App\Filament\Resources\LancamentoResource\Pages\ListLancamentos;
use App\Models\Conta;
use App\Models\Lancamento;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as Document;
use Database\Seeders\PortfolioDemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class PdfExportTest extends TestCase
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
    }

    public function test_pdf_de_lancamentos_renderiza_os_dados_selecionados_do_usuario(): void
    {
        foreach (User::all() as $user) {
            $this->app['session']->flush();
            $this->actingAs($user);
            $own = Lancamento::where('user_id', $user->id)->get();
            $selected = $own->first();
            $selected->update(['recebimento' => '1111.11']);
            $own->last()->update(['recebimento' => '2222.22']);
            $other = Lancamento::where('user_id', '!=', $user->id)->firstOrFail();
            $other->update(['recebimento' => '9999.99']);

            $this->expectDocument('lancamentos-pdf', 'lancamentos', $selected->id, $user->id, 'R$ 1.111,11', ['R$ 2.222,22', 'R$ 9.999,99']);
            Livewire::test(ListLancamentos::class)
                ->callTableBulkAction('Pdf', [$selected->id, $other->id])
                ->assertFileDownloaded('lancamentos.pdf');
        }
    }

    public function test_pdf_de_contas_renderiza_apenas_a_selecao_do_usuario(): void
    {
        foreach (User::all() as $user) {
            $this->app['session']->flush();
            $this->actingAs($user);
            $own = Conta::where('user_id', $user->id)->get();
            $selected = $own->first();
            $selected->update(['fornecedor' => 'Fornecedor selecionado']);
            $own->last()->update(['fornecedor' => 'Fornecedor não selecionado']);
            $other = Conta::where('user_id', '!=', $user->id)->firstOrFail();
            $other->update(['fornecedor' => 'Fornecedor de outro usuário']);

            $this->expectDocument('contas-pdf', 'contas', $selected->id, $user->id, 'Fornecedor selecionado', ['Fornecedor não selecionado', 'Fornecedor de outro usuário']);
            Livewire::test(ListContas::class)
                ->callTableBulkAction('Pdf', [$selected->id, $other->id])
                ->assertFileDownloaded('contas.pdf');
        }
    }

    public function test_template_de_lancamentos_gera_um_pdf_real(): void
    {
        $user = User::firstOrFail();
        $lancamentos = Lancamento::where('user_id', $user->id)->get();
        $html = view('lancamentos-pdf', compact('lancamentos'))->render();

        $this->assertStringContainsString('Relatório de lançamentos', $html);
        $this->assertStringContainsString('Dinheiro', $html);
        $this->assertStringContainsString('Bancário', $html);
        $this->assertStringContainsString('Mercadorias', $html);
        $this->assertStringContainsString('Outros', $html);
        $this->assertStringStartsWith('%PDF-', Pdf::loadView('lancamentos-pdf', compact('lancamentos'))->output());
    }

    public function test_pdfs_preservam_centavos_datas_financeiras_e_escapam_textos(): void
    {
        $contas = collect([new Conta([
            'fornecedor' => '<script>Fornecedor fictício</script>', 'descricao' => 'Serviço & manutenção',
            'valor' => '90071992547409.93', 'tipo' => '2', 'status' => '2',
            'dataVencimento' => '2026-11-20', 'dataPagamento' => null,
        ])]);
        $html = view('contas-pdf', compact('contas'))->render();
        $this->assertStringContainsString('R$ 90.071.992.547.409,93', $html);
        $this->assertStringContainsString('20/11/2026', $html);
        $this->assertStringContainsString('Não pago', $html);
        $this->assertStringContainsString('Não Operacional', $html);
        $this->assertStringContainsString('&lt;script&gt;Fornecedor fictício&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('Created At', $html);
        $this->assertStringStartsWith('%PDF-', Pdf::loadView('contas-pdf', compact('contas'))->output());

        $lancamentos = collect([new Lancamento(['recebimento' => '90071992547409.93', 'pagamento' => '0.01'])]);
        $html = view('lancamentos-pdf', compact('lancamentos'))->render();
        $this->assertStringContainsString('R$ 90.071.992.547.409,93', $html);
        $this->assertStringContainsString('R$ 0,01', $html);
        $this->assertStringContainsString('R$ 90.071.992.547.409,92', $html);
        $this->assertStringContainsString('class="data">—</td>', $html);
        $this->assertStringNotContainsString(now()->format('d/m/Y'), $html);
        $lancamentos->first()->fill(['recebimento' => '0.10', 'pagamento' => '0.20']);
        $this->assertStringContainsString('-R$ 0,10', view('lancamentos-pdf', compact('lancamentos'))->render());
    }

    public function test_pdfs_exibem_mensagem_quando_nao_ha_registros_selecionados(): void
    {
        $this->assertStringContainsString('Nenhuma conta selecionada.', view('contas-pdf', ['contas' => collect()])->render());
        $this->assertStringContainsString('Nenhum lançamento selecionado.', view('lancamentos-pdf', ['lancamentos' => collect()])->render());
    }

    private function expectDocument(string $view, string $variable, int $recordId, int $userId, string $included, array $excluded): void
    {
        $document = Mockery::mock(Document::class);
        $document->shouldReceive('output')->once()->andReturn('%PDF-documento-de-teste');
        Pdf::shouldReceive('loadView')->once()->withArgs(function (string $name, array $data) use ($view, $variable, $recordId, $userId, $included, $excluded): bool {
            $this->assertSame($view, $name);
            $this->assertSame([$recordId], $data[$variable]->pluck('id')->values()->all());
            $this->assertSame([$userId], $data[$variable]->pluck('user_id')->unique()->values()->all());
            $html = view($name, $data)->render();
            $this->assertStringContainsString($included, $html);

            foreach ($excluded as $text) {
                $this->assertStringNotContainsString($text, $html);
            }

            return true;
        })->andReturn($document);
    }
}
