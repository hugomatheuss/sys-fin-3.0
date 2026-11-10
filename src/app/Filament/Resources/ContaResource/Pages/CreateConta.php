<?php

namespace App\Filament\Resources\ContaResource\Pages;

use App\Filament\Resources\ContaResource;
use App\Models\Conta;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateConta extends CreateRecord
{
    protected static string $resource = ContaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        unset($data['user_id']);
        $data['dataPagamento'] = $data['status'] === '2' ? null : $data['dataPagamento'];

        $record = new Conta($data);
        $record->user()->associate(auth()->user());
        $record->save();

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}
