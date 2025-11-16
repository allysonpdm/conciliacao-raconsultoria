<?php

namespace App\ObjectValues;

use App\ObjectValues\Asserts\Email\Email as AssertMXEmail;
use App\ObjectValues\Contracts\{
    Obfuscatable,
    ObjectValue
};
use App\ObjectValues\Traits\Obfuscated;
use Illuminate\Mail\Message;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints\{
    Email as AssertEmail,
    NotBlank,
    Type
};
use Symfony\Component\Validator\Validation;

class Email extends ObjectValue implements Obfuscatable
{
    use Obfuscated;

    protected function validate(): void
    {
        $validator = Validation::createValidator();
        $violations = $validator->validate($this->value, [
            new NotBlank,
            new Type('string'),
            new AssertEmail(['message' => 'O e-mail não é válido.']),
            new AssertMXEmail
        ]);

        if(count($violations) > 0){
            throw new InvalidArgumentException($violations[0]->getMessage());
        }
    }

    public function __toString(): string
    {
        return Str::lower($this->value);
    }
}
