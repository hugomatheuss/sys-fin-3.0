<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lancamento extends Model
{
    use HasFactory;

    protected $fillable = [
        'recebimento',
        'pagamento',
        'tipoRecebimento',
        'tipoPagamento',
        'dataLancamento',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
