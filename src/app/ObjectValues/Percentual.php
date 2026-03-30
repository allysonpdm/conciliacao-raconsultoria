<?php

namespace App\ObjectValues;

use App\Rules\DecimalRule;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class Percentual extends Decimal
{
    protected mixed $value;
    protected ?string $decimalSeparator = '.';
    protected ?string $thousandsSeparator = '';
    protected ?int $decimals = 2;

    public function __construct(
        mixed $value = 0,
        ?int $decimals = 2
    )
    {
        parent::__construct($value, $decimals);
    }
    
    protected function validate(): void
    {
        if (!in_array($this->decimalSeparator, ['.', ','])) {
            throw new InvalidArgumentException('Invalid decimal separator');
        }

        #$validator = Validator::make(
        #    ['percentual' => $value],
        #    [
        #        'percentual' => [
        #            'required',
        #            'numeric',
        #            new DecimalRule($this->decimals),
        #            'between:0,100',
        #        ],
        #    ]
        #);
#
        #if ($validator->fails()) {
        #    throw new InvalidArgumentException($validator->errors()->first());
        #}
    }

    public function __toString(): string
    {
        return number_format(
            $this->value,
            $this->decimals,
            $this->decimalSeparator,
            $this->thousandsSeparator
        ) . '%';
    }
}
