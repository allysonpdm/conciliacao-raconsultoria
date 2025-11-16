<?php

namespace App\Rules;

use App\Models\Empresa;
use App\ObjectValues\Cnpj;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;

class UniqueCnpjForUser implements ValidationRule
{
    protected ?int $ignoreId;
    protected ?string $attributeName = null;

    public function __construct(?int $ignoreId = null)
    {
        $this->ignoreId = $ignoreId;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $this->attributeName = $attribute;

        try {
            $cnpj = new Cnpj($value);
        } catch (\InvalidArgumentException $e) {
            // Deixar outra regra (formato/CNPJ) responsabilizar-se por valores inválidos
            return;
        }

        $query = Empresa::where('user_id', Auth::id())
            ->where('cnpj', $cnpj->sanitized());

        if ($this->ignoreId) {
            $query->where('id', '<>', $this->ignoreId);
        }

        if ($query->exists()) {
            $fail($this->message());
        }
    }

    public function message()
    {
        return "O CNPJ já está cadastrado para o seu usuário.";
    }
}
