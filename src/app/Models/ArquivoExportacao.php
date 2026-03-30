<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditingAuditable;
use OwenIt\Auditing\Contracts\Auditable;

class ArquivoExportacao extends Model implements Auditable
{
    use AuditingAuditable;

    protected $table = 'arquivos_exportacoes';

    protected $fillable = [
        'conciliacao_id',
    ];

    public function conciliacao(): BelongsTo
    {
        return $this->belongsTo(related: Conciliacao::class, foreignKey: 'conciliacao_id');
    }
}
