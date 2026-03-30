<?php

namespace App\ObjectValues;

use App\ObjectValues\Asserts\CpfCnpj\CpfCnpj as AssertCpfCnpj;
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

class CpfCnpj extends ObjectValue implements Maskable, Sanitizable, Obfuscatable
{
    use Masked, Sanitized, Obfuscated;

    protected bool $isCpf;

    public function __construct(protected mixed $value)
    {
        $this->value = $value;
        $this->setRegex(new Regex('[^0-9]'));
        $this->value = $this->sanitized();
        $mask = $this->isCpf()
            ? '###.###.###-##'
            : '##.###.###/####-##';
        $this->setMask($mask);
        $this->validate($this->value);
    }

    protected function validate(): void
    {
        $validator = Validation::createValidator();
        $violations = $validator->validate($this->value, [
            new NotBlank(),
            new Type('string'),
            new AssertCpfCnpj()
        ]);
        
        if(count($violations) > 0){
            throw new InvalidArgumentException('O CPF/CNPJ é inválido.');
        }
    }

    public function isCpf(): bool
    {
        $this->isCpf = strlen($this->value) === 11;
        return $this->isCpf;
    }

    public function isCnpj(): bool
    {
        return !$this->isCpf();
    }

    public function __toString(): string
    {
        return $this->isCpf()
            ? new Cpf($this->value)
            : new Cnpj($this->value);
    }
}
