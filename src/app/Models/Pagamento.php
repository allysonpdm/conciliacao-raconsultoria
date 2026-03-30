<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditingAuditable;
use OwenIt\Auditing\Contracts\Auditable;

class Pagamento extends Model implements Auditable
{
    use AuditingAuditable;

    protected $table = 'pagamentos';

    protected $fillable = [
        'conta_conciliada_id',
        'data',
        'doc',
        'parcela',
        'numero_nota',
        'valor_nota',
        'valor_pago',
        'valor_juros',
        'valor_descontos',
        'code',
        'tipo',
    ];

    protected $casts = [
        'conta_conciliada_id' => 'integer',
        'data' => 'date',
        'valor_nota' => 'decimal:2',
        'valor_pago' => 'decimal:2',
        'valor_juros' => 'decimal:2',
        'valor_descontos' => 'decimal:2',
    ];

    public function contaConciliada(): BelongsTo
    {
        return $this->belongsTo(related: ContaConciliada::class, foreignKey: 'conta_conciliada_id');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(related: Nota::class, foreignKey: 'numero', localKey: 'numero_nota')
            ->where('conta_conciliada_id', $this->conta_conciliada_id);
    }

    public function sugestao(): HasMany
    {
        return $this->hasMany(
            related: ErroPagamento::class,
            foreignKey: 'sugestao_numero_nota',
            localKey: 'numero_nota'
        )
        ->where('erros_pagamentos.conta_conciliada_id', $this->conta_conciliada_id);
    }

}
