<?php

namespace App\ObjectValues;

use App\ObjectValues\Asserts\Cnpj\Cnpj as AssertCnpj;
use App\ObjectValues\Contracts\{
    Maskable,
    Obfuscatable,
    ObjectValue,
    Sanitizable
};
use App\ObjectValues\Traits\{
    Masked,
    Obfuscated,
    Sanitized
};
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints\{
    NotBlank,
    Type
};
use Symfony\Component\Validator\Validation;

class Cnpj extends ObjectValue implements Maskable, Sanitizable, Obfuscatable
{
    use Masked, Sanitized, Obfuscated;

    public function __construct(protected mixed $value)
    {
        if ($value instanceof self) {
            $value = (string) $value;
        }

        parent::__construct($value);
        $this->setMask('##.###.###/####-##');
        $this->setRegex('[^0-9]');
        $this->value = $this->sanitized();
    }

    protected function validate(): void
    {
        if ($this->value instanceof self) {
            return;
        }

        $validator = Validation::createValidator();
        $violations = $validator->validate($this->value, [
            new NotBlank(),
            new Type('string'),
            new AssertCnpj()
        ]);

        if(count($violations) > 0){
            throw new InvalidArgumentException('O CNPJ é inválido.');
        }
    }

    public function __toString(): string
    {
        return str_pad($this->value, 14, '0', STR_PAD_LEFT);
    }

}
