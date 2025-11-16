<?php

namespace App\ObjectValues;

use App\ObjectValues\Asserts\Decimal\Decimal as AssertDecimal;
use App\ObjectValues\Contracts\ObjectValue;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints\{
    NotBlank,
    Type
};
use Symfony\Component\Validator\Validation;

class Decimal extends ObjectValue
{
    protected mixed $value;
    protected ?string $decimalSeparator = '.';
    protected ?string $thousandsSeparator = '';

    public function __construct(
        mixed $value = 0,
        protected ?int $decimals = 2
    )
    {
        parent::__construct($value);
    }

    public function setDecimalSeparator(string $separator): void
    {
        if (!in_array($separator, ['.', ','])) {
            throw new InvalidArgumentException('Invalid decimal separator');
        }

        $this->decimalSeparator = $separator;
    }

    public function setThousandsSeparator(string $separator): void
    {
        $this->thousandsSeparator = $separator;
    }

    protected function validate(): void
    {
        if (!in_array($this->decimalSeparator, ['.', ','])) {
            throw new InvalidArgumentException('Invalid decimal separator');
        }

        $validator = Validation::createValidator();
        $violations = $validator->validate($this->value, [
            new NotBlank(),
            new Type('numeric'),
            new AssertDecimal(decimalPlaces: $this->decimals)
        ]);

        
        if(count($violations) > 0){
            throw new InvalidArgumentException($violations[0]->getMessage());
        }
    }

    public function __toString()
    {
        return number_format(
            $this->value,
            $this->decimals,
            $this->decimalSeparator,
            $this->thousandsSeparator
        );
    }
}
