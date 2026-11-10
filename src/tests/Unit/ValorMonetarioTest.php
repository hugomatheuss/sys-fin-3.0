<?php

namespace Tests\Unit;

use App\Support\ValorMonetario;
use PHPUnit\Framework\TestCase;

class ValorMonetarioTest extends TestCase
{
    public function test_normaliza_entradas_sem_float_ou_arredondamento(): void
    {
        foreach ([
            'R$ 1.234,56' => '1234.56',
            '1.234' => '1234.00',
            '00012,3' => '12.30',
            '0,01' => '0.01',
            '0' => '0.00',
            '-0,00' => '0.00',
            '-12,50' => '-12.50',
            '12.5' => '12.50',
            '90071992547409.93' => '90071992547409.93',
        ] as $entrada => $esperado) {
            $this->assertSame($esperado, ValorMonetario::normalizar((string) $entrada));
        }
    }

    public function test_recusa_entradas_invalidas_sem_arredondar(): void
    {
        foreach (['', 'abc', '1e3', '1,234', '0.001', '12.34,56', '1,2,3', '--1', 'NaN', '1.234,567'] as $entrada) {
            $this->assertNull(ValorMonetario::normalizar($entrada));
        }
    }

    public function test_formata_brl_sem_perder_centavos(): void
    {
        $this->assertSame('R$ 0,00', ValorMonetario::formatar('0.00'));
        $this->assertSame('-R$ 0,01', ValorMonetario::formatar('-0.01'));
        $this->assertSame('R$ 1.234,56', ValorMonetario::formatar('1234.56'));
        $this->assertSame('R$ 90.071.992.547.409,93', ValorMonetario::formatar('90071992547409.93'));
    }
}
