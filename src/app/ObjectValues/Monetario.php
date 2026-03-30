<?php

namespace App\ObjectValues;

class Monetario extends Decimal
{
    protected mixed $value;
    protected ?string $symbol = null;
    protected ?string $thousandsSeparator = '.';
    protected ?string $decimalSeparator = ',';

    public function __construct(
        mixed $value = 0,
        ?int $decimals = 2
    )
    {
        parent::__construct($value, $decimals);
    }

    public function setSymbol(?string $symbol = null): void
    {
        $this->symbol = trim($symbol);
    }

    public function setSeparators(
        ?string $thousandsSeparator = null,
        ?string $decimalSeparator = null
    ): void
    {
        $this->thousandsSeparator = $thousandsSeparator;
        $this->decimalSeparator = $decimalSeparator;
    }

    public function __toString(): string
    {
        $formatted = number_format(
            $this->value,
            $this->decimals,
            $this->decimalSeparator,
            $this->thousandsSeparator
        );

        if (!empty($this->symbol)) {
            $formatted = "{$this->symbol} $formatted";
        }

        return $formatted;
    }
}
