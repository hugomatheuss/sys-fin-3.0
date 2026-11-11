<?php

namespace App\Filament\Exports;

use Filament\Actions\Exports\Exporter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class ExportadorFinanceiro extends Exporter
{
    public function getFileDisk(): string
    {
        return 'local';
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->where('user_id', auth()->id());
    }

    public function __invoke(Model $record): array
    {
        $ownerId = $this->export->getAttribute('user_id');

        if ($ownerId === null || (string) $record->getAttribute('user_id') !== (string) $ownerId) {
            throw new AuthorizationException('Registro indisponível para esta exportação.');
        }

        return parent::__invoke($record);
    }
}
