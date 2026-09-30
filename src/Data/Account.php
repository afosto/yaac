<?php

namespace Afosto\Acme\Data;

use Psr\Http\Message\ResponseInterface;

class Account
{
    public const STATUS_VALID = 'valid';
    public const STATUS_INVALID = 'deactivated';
    public const STATUS_REVOKED = 'revoked';

    /**
     * @var string
     */
    protected $createdAt;
    
    /**
     * @var string
     */
    protected $status;

    /**
     * @var string
     */
    protected $accountURL;

    public function __construct(
        ResponseInterface $response
    ) {
        $data = json_decode((string)$response->getBody(), true);
        $this->accountURL = $response->getHeaderLine('Location');
        $this->createdAt = (new \DateTime())->setTimestamp(strtotime($data['createdAt']));
        $this->status = $data['status'];
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
     * Return the status for the account
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Returns validation status
     * @return bool
     */
    public function isInvalid(): bool
    {
        return $this->status === self::STATUS_INVALID;
    }

    /**
     * Returns revoked status
     * @return bool
     */
    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    /**
     * Returns valid status
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->status === self::STATUS_VALID;
    }
}
