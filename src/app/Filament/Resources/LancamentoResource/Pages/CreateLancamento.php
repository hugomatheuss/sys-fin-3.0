<?php

namespace App\Filament\Resources\LancamentoResource\Pages;

use App\Filament\Resources\LancamentoResource;
use App\Models\Lancamento;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLancamento extends CreateRecord
{
    protected static string $resource = LancamentoResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        unset($data['user_id']);

        $record = new Lancamento($data);
        $record->user()->associate(auth()->user());
        $record->save();

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}
