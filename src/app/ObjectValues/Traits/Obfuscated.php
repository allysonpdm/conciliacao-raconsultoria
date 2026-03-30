<?php

namespace App\ObjectValues\Traits;

trait Obfuscated
{
    protected mixed $value;
    protected string $obfuscationCharacter = '*';

    public function obfuscated(int $visiblePrefixDigits = 0, int $visibleSuffixDigits = 0): string
    {
        $value = (string) $this->value;
        $length = strlen($value);
        $visiblePrefix = substr($value, 0, $visiblePrefixDigits);
        $visibleSuffix = substr($value, -$visibleSuffixDigits);

        $obfuscatedPortion = str_repeat(
            $this->obfuscationCharacter,
            $length - ($visiblePrefixDigits + $visibleSuffixDigits)
        );

        return $visiblePrefix . $obfuscatedPortion . $visibleSuffix;
    }

    public function setObfuscationCharacter(string $character): void
    {
        $this->obfuscationCharacter = $character;
    }
}
