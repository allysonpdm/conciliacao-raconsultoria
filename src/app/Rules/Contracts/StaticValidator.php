<?php

namespace App\Rules\Contracts;

interface StaticValidator
{
    public static function isValid(?string $value = null): bool;
}
