<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditingAuditable;
use OwenIt\Auditing\Contracts\Auditable;

class Nota extends Model implements Auditable
{
    use AuditingAuditable;

    protected $table = 'notas';

    protected $fillable = [
        'conta_conciliada_id',
        'numero',
        'data',
        'valor',
        'valor_pago',
        'tipo'
    ];

    protected $casts = [
        'conta_conciliada_id' => 'integer',
        'data' => 'date',
        'valor' => 'decimal:2',
        'valor_pago' => 'decimal:2',
    ];

    public function contaConciliada(): BelongsTo
    {
        return $this->belongsTo(related: ContaConciliada::class, foreignKey: 'conta_conciliada_id');
    }

    public function pagamentos()
    {
        return $this->hasMany(related: Pagamento::class, foreignKey: 'numero_nota', localKey: 'numero')
            ->where('conta_conciliada_id', $this->conta_conciliada_id);
    }
}
