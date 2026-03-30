<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErroPagamento extends Model
{
    protected $table = 'erros_pagamentos';

    protected $fillable = [
        'conta_conciliada_id',
        'data',
        'doc',
        'numero_nota',
        'valor_pago',
        'sugestao_numero_nota'
    ];

    protected $casts = [
        'conta_conciliada_id' => 'integer',
        'data' => 'date',
        'valor_pago' => 'decimal:2',
    ];

    public function contaConciliada(): BelongsTo
    {
        return $this->belongsTo(related: ContaConciliada::class, foreignKey: 'conta_conciliada_id');
    }
}
