<?php

namespace App\Support;

use InvalidArgumentException;

class ValorMonetario
{
    public static function normalizar(string $valor): ?string
    {
        $valor = preg_replace('/^R\$\s*/u', '', trim($valor));

        if (str_contains($valor, ',')) {
            if (! preg_match('/^-?(?:\d+|[1-9]\d{0,2}(?:\.\d{3})+),\d{1,2}$/', $valor)) {
                return null;
            }

            $valor = str_replace(['.', ','], ['', '.'], $valor);
        } elseif (preg_match('/^-?[1-9]\d{0,2}(?:\.\d{3})+$/', $valor)) {
            $valor = str_replace('.', '', $valor);
        } elseif (! preg_match('/^-?\d+(?:\.\d{1,2})?$/', $valor)) {
            return null;
        }

        $negativo = str_starts_with($valor, '-');
        [$inteiro, $centavos] = array_pad(explode('.', ltrim($valor, '-'), 2), 2, '00');
        $inteiro = ltrim($inteiro, '0') ?: '0';
        $centavos = str_pad($centavos, 2, '0');
        $sinal = $negativo && ($inteiro !== '0' || $centavos !== '00') ? '-' : '';

        return $sinal.$inteiro.'.'.$centavos;
    }

    public static function formatar(string $valor): string
    {
        $valor = self::normalizar($valor);

        if ($valor === null) {
            throw new InvalidArgumentException('Valor monetário inválido.');
        }

        $sinal = str_starts_with($valor, '-') ? '-' : '';
        [$inteiro, $centavos] = explode('.', ltrim($valor, '-'));
        $inteiro = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $inteiro);

        return $sinal.'R$ '.$inteiro.','.$centavos;
    }
}
