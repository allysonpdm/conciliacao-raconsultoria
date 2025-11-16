<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Auditable as AuditingAuditable;
use OwenIt\Auditing\Contracts\Auditable;

class ContaConciliada extends Model implements Auditable
{
    use AuditingAuditable;

    protected $table = 'contas_conciliadas';

    protected $fillable = [
        'conciliacao_id',
        'numero',
        'nome',
        'mascara_contabil',
        'balanceado'
    ];

    protected $casts = [
        'balanceado' => 'boolean',
    ];

    public function ajustes(): HasMany
    {
        return $this->hasMany(related: Ajuste::class, foreignKey: 'conta_conciliada_id');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(related: Nota::class, foreignKey: 'conta_conciliada_id');
    }

    public function pagamentos(): HasMany
    {
        return $this->hasMany(related: Pagamento::class, foreignKey: 'conta_conciliada_id');
    }

    public function errosPagamentos(): HasMany
    {
        return $this->hasMany(related: ErroPagamento::class, foreignKey: 'conta_conciliada_id');
    }
}
