<?php

namespace App\Filament\Forms\Components;

use App\Support\ValorMonetario;
use Closure;
use Filament\Forms\Components\TextInput;

class ValorMonetarioInput extends TextInput
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prefix('R$')
            ->inputMode('decimal')
            ->placeholder('0,00')
            ->helperText('Use vírgula para os centavos, por exemplo: 1.234,56.')
            ->default('0,00')
            ->required()
            ->maxLength(255)
            ->rules([
                'bail',
                'string',
                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    $normalizado = is_string($value) ? ValorMonetario::normalizar($value) : null;

                    if ($normalizado === null) {
                        $fail('Informe um valor monetário válido com até duas casas decimais.');
                    } elseif (strlen($normalizado) > 255) {
                        $fail('O valor excede o limite de armazenamento.');
                    }
                },
            ])
            ->formatStateUsing(function (mixed $state): mixed {
                if (! is_string($state)) {
                    return null;
                }

                if (ValorMonetario::normalizar($state) === null) {
                    return $state;
                }

                return str_replace('R$ ', '', ValorMonetario::formatar($state));
            })
            ->dehydrateStateUsing(fn (string $state): string => ValorMonetario::normalizar($state));
    }
}
