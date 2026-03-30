<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditingAuditable;
use OwenIt\Auditing\Contracts\Auditable;

class Ajuste extends Model implements Auditable
{
    use AuditingAuditable;
    protected $table = 'ajustes_anteriores';
    protected $fillable = [
        'conta_conciliada_id',
        'data',
        'doc',
        'numero_nota',
        'valor',
        'tipo'
    ];

    protected $casts = [
        'data' => 'datetime',
        'valor' => 'decimal:2'
    ];

    public function conta(): BelongsTo
    {
        return $this->belongsTo(related: ContaConciliada::class);
    }
}
