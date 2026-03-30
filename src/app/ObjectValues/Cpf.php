<?php

namespace App\ObjectValues;

use App\ObjectValues\Asserts\Cpf\Cpf as AssertCpf;
use App\Rules\CpfValidationRule;
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

class Cpf extends ObjectValue implements Maskable, Sanitizable, Obfuscatable
{
    use Masked, Sanitized, Obfuscated;

    public function __construct(protected mixed $value)
    {
        parent::__construct($value);
        $this->setMask('###.###.###-##');
        $this->setRegex('[^0-9]');
        $this->value = $this->sanitized();
    }

    protected function validate(): void
    {
        $validator = Validation::createValidator();
        $violations = $validator->validate($this->value, [
            new NotBlank(),
            new Type('string'),
            new AssertCpf()
        ]);
        
        if(count($violations) > 0){
            throw new InvalidArgumentException('O CPF é inválido.');
        }
    }

    public function __toString(): string
    {
        return str_pad($this->value, 11, '0', STR_PAD_LEFT);
    }

}
