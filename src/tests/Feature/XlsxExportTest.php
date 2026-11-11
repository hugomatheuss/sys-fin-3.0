<?php

namespace Tests\Feature;

use App\Filament\Exports\ContasNaoPagasExporter;
use App\Filament\Exports\ContasPagasExporter;
use App\Filament\Exports\LancamentoExporter;
use App\Filament\Resources\ContaResource\Pages\ListContas;
use App\Filament\Resources\LancamentoResource\Pages\ListLancamentos;
use App\Models\Conta;
use App\Models\Lancamento;
use App\Models\User;
use Database\Seeders\PortfolioDemoSeeder;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class XlsxExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            $this->markTestSkipped('Execute com phpunit.portfolio.xml: os testes exigem SQLite em memória.');
        }

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->seed(PortfolioDemoSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::firstOrFail());
    }

    public function test_xlsx_de_contas_respeita_filtros_status_e_usuario(): void
    {
        foreach (User::all() as $user) {
            $this->app['session']->flush();
            $this->actingAs($user);
            foreach (['1' => 'relatorioContasPagas', '2' => 'relatorioContasNaoPagas'] as $status => $action) {
                $record = Conta::where('user_id', $user->id)->where('status', $status)->firstOrFail();
                $record->update(['fornecedor' => 'Fornecedor alvo', 'valor' => '90071992547409.93', 'dataVencimento' => '2026-11-10']);
                $other = Conta::where('user_id', '!=', $user->id)->firstOrFail();
                $other->update(['fornecedor' => 'Fornecedor alvo', 'status' => (string) $status]);

                Livewire::test(ListContas::class)
                    ->filterTable('fornecedor', ['fornecedor' => 'Fornecedor alvo'])
                    ->filterTable('Valor', ['valor' => '90.071.992.547.409,93'])
                    ->callTableAction($action)->assertHasNoTableActionErrors();

                $export = Export::latest('id')->firstOrFail();
                $this->assertSame($user->id, $export->user_id);
                $this->assertSame('local', $export->file_disk);
                $this->assertSame(1, $export->successful_rows);
                $this->assertNotNull($export->completed_at);
                $rows = $this->readRows($export);
                $this->assertCount(2, $rows);
                $this->assertSame('Fornecedor alvo', $rows[1][0]);
                $this->assertSame('90071992547409.93', $rows[1][1]);
                $this->assertSame((string) $status === '1' ? 'Pago' : 'Não pago', $rows[1][3]);
                $this->assertSame('10/11/2026', $rows[1][6]);
            }
        }
    }

    public function test_xlsx_de_lancamentos_respeita_filtro_e_permissoes_de_download(): void
    {
        foreach (User::all() as $user) {
            $this->app['session']->flush();
            $this->actingAs($user);
            $records = Lancamento::where('user_id', $user->id)->get();
            foreach ($records as $record) {
                $record->update(['tipoRecebimento' => '0']);
            }
            $selected = $records->first();
            $selected->update(['tipoRecebimento' => '1', 'recebimento' => '1234.56', 'pagamento' => '0.10', 'dataLancamento' => '2026-11-10']);
            Livewire::test(ListLancamentos::class)->filterTable('Dinheiro')->callTableAction('export')->assertHasNoTableActionErrors();
            $export = Export::latest('id')->firstOrFail();
            $rows = $this->readRows($export);
            $this->assertCount(2, $rows);
            $this->assertSame(['1234.56', '0.10', 'Dinheiro', 'Mercadorias', '10/11/2026'], array_slice($rows[1], 0, 5));
            $url = route('filament.exports.download', ['export' => $export, 'format' => 'xlsx']);
            $response = $this->get($url)->assertOk()->assertDownload($export->file_name.'.xlsx');
            $this->assertStringStartsWith('PK', $response->streamedContent());
            $this->app['session']->flush();
            $this->actingAs(User::where('id', '!=', $user->id)->firstOrFail());
            $this->get($url)->assertForbidden();
        }
    }

    public function test_exportadores_isolam_consultas_sem_depender_da_tabela(): void
    {
        foreach ([ContasPagasExporter::class, ContasNaoPagasExporter::class, LancamentoExporter::class] as $exporter) {
            $records = $exporter::modifyQuery($exporter::getModel()::query())->get();
            $this->assertNotEmpty($records);
            $this->assertSame([auth()->id()], $records->pluck('user_id')->unique()->values()->all());
        }
    }

    public function test_exportador_rejeita_registro_de_outro_proprietario(): void
    {
        $export = new Export(['user_id' => auth()->id()]);
        $columnMap = collect(ContasPagasExporter::getColumns())->mapWithKeys(fn (ExportColumn $column): array => [$column->getName() => $column->getLabel()])->all();
        $exporter = new ContasPagasExporter($export, $columnMap, []);
        $other = Conta::where('user_id', '!=', auth()->id())->firstOrFail();
        $this->expectException(AuthorizationException::class);
        $exporter($other);
    }

    public function test_data_ausente_nao_e_exportada_como_1970(): void
    {
        $record = Lancamento::where('user_id', auth()->id())->firstOrFail();
        $record->update(['dataLancamento' => null]);
        $export = new Export(['user_id' => auth()->id()]);
        $exporter = new LancamentoExporter($export, ['dataLancamento' => 'Data'], []);
        $this->assertSame([null], $exporter($record->fresh()));
    }

    public function test_linhas_sao_verificadas_pelo_dono_da_exportacao_sem_sessao(): void
    {
        $userId = auth()->id();
        $record = Conta::where('user_id', $userId)->firstOrFail();
        $export = new Export(['user_id' => $userId]);
        $exporter = new ContasPagasExporter($export, ['valor' => 'Valor'], []);
        app('auth')->forgetGuards();
        $this->assertNull(auth()->user());
        $this->assertSame([$record->valor], $exporter($record));
    }

    public function test_consultas_sem_usuario_nao_exportam_registros(): void
    {
        app('auth')->forgetGuards();
        $this->assertNull(auth()->user());
        foreach ([ContasPagasExporter::class, ContasNaoPagasExporter::class, LancamentoExporter::class] as $exporter) {
            $this->assertSame(0, $exporter::modifyQuery($exporter::getModel()::query())->count());
        }
    }

    public function test_download_sem_autenticacao_e_recusado(): void
    {
        $export = Export::create([
            'user_id' => auth()->id(), 'exporter' => ContasPagasExporter::class,
            'file_disk' => 'local', 'file_name' => 'contas', 'total_rows' => 0,
        ]);
        app('auth')->forgetGuards();
        $this->assertNull(auth()->user());
        $url = route('filament.exports.download', ['export' => $export, 'format' => 'xlsx']);
        $this->get($url)->assertRedirect(route('filament.admin.auth.login'));
        $this->getJson($url)->assertUnauthorized();
    }

    private function readRows(Export $export): array
    {
        $path = $export->getFileDirectory().'/'.$export->file_name.'.xlsx';
        $export->getFileDisk()->assertExists($path);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $reader = new Reader;
        $reader->open(Storage::disk($export->file_disk)->path($path));
        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(fn ($cell) => $cell->getValue(), $row->getCells());
                }
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }
}
