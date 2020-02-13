<?php

namespace Afosto\Acme\Data;

class Account
{

    /**
     * @var array
     */
    protected $contact;

    /**
     * @var string
     */
    protected $createdAt;

    /**
     * @var bool
     */
    protected $isValid;

    /**
     * @var
     */
    protected $initialIp;

    /**
     * @var string
     */
    protected $accountURL;


    public function __construct(
        array $contact,
        \DateTime $createdAt,
        bool $isValid,
        string $initialIp,
        string $accountURL
    ) {
        $this->initialIp = $initialIp;
        $this->contact = $contact;
        $this->createdAt = $createdAt;
        $this->isValid = $isValid;
        $this->accountURL = $accountURL;
    }

    public function getId(): string
    {
        return substr($this->accountURL, strrpos($this->accountURL, '/') + 1);
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }


    public function getAccountURL(): string
    {
        return $this->accountURL;
    }

    /**
     * @return array
     */
    public function getContact(): array
    {
        return $this->contact;
    }

    /**
     * @return string
     */
    public function getInitialIp(): string
    {
        return $this->initialIp;
    }

    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }
}
