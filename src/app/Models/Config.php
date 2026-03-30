<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable as AuditingAuditable;
use OwenIt\Auditing\Contracts\Auditable;

class Config extends Model implements Auditable
{
    use AuditingAuditable;

    protected $table = 'configs';

    protected $fillable = [
        'empresa_id',
        'conta_juros',
        'conta_descontos',
        'conta_pagamentos',
        'codigo_historico_juros',
        'codigo_historico_descontos',
        'codigo_historico_pagamentos',
        'parcela_preferencial_set',
        'parcela_preferencial_get',
        'percentual_min_pago',
        'meses_tolerancia_desconto',
        'meses_tolerancia_sem_pagamentos',
        'parcelar',
        'valor_minimo_parcela',
        'numero_maximo_parcelas',
        'inicio_periodo_pagamento',
        'fim_periodo_pagamento',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'percentual_min_pago' => 'decimal:2',
        'meses_tolerancia_desconto' => 'integer',
        'meses_tolerancia_sem_pagamentos' => 'integer',
        'parcelar' => 'boolean',
        'valor_minimo_parcela' => 'decimal:2',
        'numero_maximo_parcelas' => 'integer',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(related: Empresa::class);
    }
}
