<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Auditable as AuditingAuditable;
use OwenIt\Auditing\Contracts\Auditable;

class Empresa extends Model implements Auditable
{
    use AuditingAuditable;

    protected $table = 'empresas';
    protected $fillable = [
        'user_id',
        'nome',
        'cnpj',
        'conta_conciliacao',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(related: User::class);
    }

    public function config(): HasOne
    {
        return $this->hasOne(related: Config::class);
    }

    public function conciliacao(): HasOne
    {
        return $this->hasOne(related: Conciliacao::class);
    }

    public function contas(): HasManyThrough
    {
        return $this->hasManyThrough(
            related: ContaConciliada::class,
            through: Conciliacao::class,
            firstKey: 'empresa_id',
            secondKey: 'conciliacao_id',
        );
    }
}
