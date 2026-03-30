<?php

namespace App\ObjectValues\Asserts\Decimal;

use App\Rules\{
    CnpjValidationRule,
    CpfValidationRule,
    DecimalRule
};
use InvalidArgumentException;
use Symfony\Component\Validator\{
    Constraint,
    ConstraintValidator
};

class DecimalValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if(
            !is_numeric($value) ||
            !is_float($value) &&
            !is_integer($value)
        ){
            throw new InvalidArgumentException('Um valor do tipo decimal deve ser informado.');
        }

        if (
            !DecimalRule::isValid($value)
        ) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
