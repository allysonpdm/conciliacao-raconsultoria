<?php

namespace App\Rules;

use App\Rules\Contracts\StaticValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CpfValidationRule implements ValidationRule, StaticValidator
{
    public static string $message = 'O :attribute não é válido.';

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     * @return void
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if(!self::isValid($value)){
            $fail(self::$message);
        }
    }

    public static function isValid(?string $cpf = null): bool
    {
        // Verifica se está vazio
        if (empty($cpf)) {
            return false;
        }

        // Elimina possível mascara
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        $cpf = str_pad($cpf, 11, '0', STR_PAD_LEFT);

        if (
            strlen($cpf) != 11 || // Verifica se o numero de dígitos informados é igual a 11
            preg_match('/(\d)\1{11}/', $cpf) // Verifica se todos os dígitos do CPF são iguais
        ) {
            return false;
        }

        // Calcula os dígitos verificadores para verificar se o CPF é válido
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }

        return true;
    }

}
