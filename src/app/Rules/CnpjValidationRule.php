<?php

namespace App\Rules;

use App\Rules\Contracts\StaticValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CnpjValidationRule implements ValidationRule, StaticValidator
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

    public static function isValid(?string $cnpj = null): bool
    {
        // Verifica se está vazio
        if (empty($cnpj)) {
            return false;
        }

        // Remove todos os caracteres que não são dígitos do valor do CNPJ;
        $value = preg_replace('/[^0-9]/', '', $cnpj);

        if (
            strlen($value) != 14 || // Verifica se o CNPJ tem 14 dígitos;
            preg_match('/(\d)\1{13}/', $value) // Verifica se todos os dígitos do CNPJ são iguais;
        ) {
            return false;
        }

        // Verifica o primeiro dígito verificador do CNPJ;
        $sum = 0;
        $multiplier = 5;

        for ($i = 0; $i < 12; $i++) {
            $sum += $value[$i] * $multiplier;
            $multiplier = $multiplier == 2 ? 9 : $multiplier - 1;
        }

        $remainder = $sum % 11;
        $digit = $remainder < 2 ? 0 : 11 - $remainder;

        if ($value[12] != $digit) {
            return false;
        }

        // Verifica o segundo dígito verificador do CNPJ;
        $sum = 0;
        $multiplier = 6;

        for ($i = 0; $i < 13; $i++) {
            $sum += $value[$i] * $multiplier;
            $multiplier = $multiplier == 2 ? 9 : $multiplier - 1;
        }

        $remainder = $sum % 11;
        $digit = $remainder < 2 ? 0 : 11 - $remainder;

        return $value[13] == $digit;
    }
}
