<?php

namespace Afosto\Acme\Data;

class Account
{
    /**
     * @var string
     */
    protected $createdAt;

    /**
     * @var bool
     */
    protected $isValid;

    /**
     * @var string
     */
    protected $accountURL;


    /**
     * Account constructor.
     * @param \DateTime $createdAt
     * @param bool $isValid
     * @param string $accountURL
     */
    public function __construct(
        \DateTime $createdAt,
        bool $isValid,
        string $accountURL
    ) {
        $this->createdAt = $createdAt;
        $this->isValid = $isValid;
        $this->accountURL = $accountURL;
    }

    /**
     * Return the account ID
     * @return string
     */
    public function getId(): string
    {
        return substr($this->accountURL, strrpos($this->accountURL, '/') + 1);
    }

    /**
     * Return create date for the account
     * @return \DateTime
     */
    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    /**
     * Return the URL for the account
     * @return string
     */
    public function getAccountURL(): string
    {
        return $this->accountURL;
    }

    /**
     * @deprecated
     * Return contact data
     * @return array
     */
    public function getContact(): array
    {
        return [];
    }

    /**
     * @deprecated
     *
     * Return initial IP
     * @return string
     */
    public function getInitialIp(): string
    {
        return '';
    }

    /**
     * Returns validation status
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }
}
