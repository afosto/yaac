<?php

namespace Afosto\Acme\Data;

class Account
{
    public function __construct(
        protected \DateTime $createdAt,
        protected bool $isValid,
        protected string $accountURL
    ) {
    }

    /**
     * Return the account ID
     */
    public function getId(): string
    {
        return substr($this->accountURL, strrpos($this->accountURL, '/') + 1);
    }

    /**
     * Return create date for the account
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * Return the URL for the account
     */
    public function getAccountURL(): string
    {
        return $this->accountURL;
    }

    /**
     * Returns validation status
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }
}
