<?php

namespace Afosto\Acme\Data;

class Account extends Data
{

    /**
     * @var array
     */
    protected $contact;

    /**
     * @var \DateTime
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


    /**
     * Account constructor.
     * @param array $contact
     * @param \DateTime $createdAt
     * @param bool $isValid
     * @param string $initialIp
     * @param string $accountURL
     */
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
     * Return contact data
     * @return array
     */
    public function getContact(): array
    {
        return $this->contact;
    }

    /**
     * Return initial IP
     * @return string
     */
    public function getInitialIp(): string
    {
        return $this->initialIp;
    }

    /**
     * Returns validation status
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * @param string $json
     * @return Account
     * @throws \Exception
     */
    public static function fromJson($json)
    {
        $data = json_decode($json, true);

        return new Account(
            $data['contact'],
            new \DateTime($data['createdAt']['date']),
            $data['isValid'],
            $data['initialIp'],
            $data['accountURL']
        );
    }
}
