<?php

namespace App\Filament\Resources\ContaResource\Pages;

use App\Filament\Resources\ContaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditConta extends EditRecord
{
    protected static string $resource = ContaResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['user_id']);
        $data['dataPagamento'] = $data['status'] === '2' ? null : $data['dataPagamento'];

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}
