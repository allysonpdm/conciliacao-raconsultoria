<?php

namespace App\Rules;

use App\Models\Empresa;
use App\ObjectValues\Cnpj;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueContaConciliacaoForUser implements ValidationRule
{
    protected ?int $ignoreId;
    protected ?string $attributeName = null;
    protected ?Empresa $empresa = null;

    public function __construct(?int $ignoreId = null)
    {
        $this->ignoreId = $ignoreId;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->attributeName = $attribute;

        $query = Empresa::where('user_id', Auth::id())
            ->where('conta_conciliacao', $value);

        if ($this->ignoreId) {
            $query->where('id', '<>', $this->ignoreId);
        }

        if ($query->exists()) {
            $this->empresa = $query->first();
            $fail($this->message());
        }
    }

    public function message()
    {
        $cnpj = new Cnpj($this->empresa->cnpj);
        return "Essa conta já está sendo utilizada pela empresa: {$cnpj->masked()} - {$this->empresa->nome}.";
    }
}
