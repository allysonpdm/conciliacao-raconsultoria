<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Auditable as AuditingAuditable;
use OwenIt\Auditing\Contracts\Auditable;

class Conciliacao extends Model implements Auditable
{
    use AuditingAuditable;

    protected $table = 'conciliacoes';
    protected $fillable = [
        'empresa_id',
        'file'
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(related: Empresa::class);
    }

    public function contas(): HasMany
    {
        return $this->hasMany(related: ContaConciliada::class, foreignKey: 'conciliacao_id');
    }

    public function pagamentos(): HasManyThrough
    {
        return $this->hasManyThrough(related: Pagamento::class, through: ContaConciliada::class, firstKey: 'conciliacao_id', secondKey: 'conta_conciliada_id');
    }

    public function notas(): HasManyThrough
    {
        return $this->hasManyThrough(related: Nota::class, through: ContaConciliada::class, firstKey: 'conciliacao_id', secondKey: 'conta_conciliada_id');
    }

    public function ajustes(): HasManyThrough
    {
        return $this->hasManyThrough(related: Ajuste::class, through: ContaConciliada::class, firstKey: 'conciliacao_id', secondKey: 'conta_conciliada_id');
    }

    public function possiveisErrosPagamento(): HasManyThrough
    {
        return $this->hasManyThrough(related: ErroPagamento::class, through: ContaConciliada::class, firstKey: 'conciliacao_id', secondKey: 'conta_conciliada_id');
    }

    public function arquivoExportacao(): HasOne
    {
        return $this->hasOne(related: ArquivoExportacao::class, foreignKey: 'conciliacao_id');
    }
}
