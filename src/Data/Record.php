<?php

namespace Afosto\Acme\Data;

class Record
{
    public function __construct(
        protected string $name,
        protected string $value,
    ) {
    }

    /**
     * Return the DNS TXT record name for validation
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Return the record value for DNS validation
     */
    public function getValue(): string
    {
        return $this->value;
    }
}
