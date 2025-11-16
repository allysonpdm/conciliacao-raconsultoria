<?php

namespace App\ObjectValues\Contracts;

interface Obfuscatable {
    /**
     * Returns the obfuscated string.
     *
     * @return string
     */
    public function obfuscated(int $visiblePrefixDigits, int $visibleSuffixDigits): string;

    /**
     * Change the character used in obfuscation.
     *
     * @return void
     */
    public function setObfuscationCharacter(string $character): void;
}
