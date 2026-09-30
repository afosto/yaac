<?php

namespace Afosto\Acme\Data;

use Afosto\Acme\Data\Challenge\Error as ChallengeError;

class Challenge
{
    public const TYPE_DNS = 'dns-01';
    public const TYPE_HTTP = 'http-01';
    public const TYPE_TLS_ALPN = 'tls-alpn-01';
    public const TYPE_DNS_PERSIST = 'dns-persist-01';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_VALID = 'valid';
    public const STATUS_INVALID = 'invalid';

    /**
     * @var string
     */
    protected $authorizationURL;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $token = '';

    /**
     * @var array<string>
     */
    protected $issuerDomainNames = [];

    /** 
     * @var null|ChallengeError
     */
    protected $error;

    /**
     * @var array|null
     */
    protected $validationRecord;

    /**
     * @var string|null
     */
    protected $validated;

    /**
     * Challenge constructor.
     * @param string $authorizationURL
     * @param array $data
     * @throws \InvalidArgumentException
     */
    public function __construct(string $authorizationURL, array $data)
    {
        $this->authorizationURL = $authorizationURL;

        switch($data['type'] ?? ''){
            case self::TYPE_DNS:
            case self::TYPE_HTTP:
            case self::TYPE_TLS_ALPN:
                $mandatoryData = [
                    'type' => 'type',
                    'status' => 'status',
                    'url' => 'url',
                    'token' => 'token'
                ];
                break;
            case self::TYPE_DNS_PERSIST:
                $mandatoryData = [
                    'type' => 'type',
                    'status' => 'status',
                    'url' => 'url',
                    'issuer-domain-names' => 'issuerDomainNames'
                ];
                break;
            default:
                throw new \InvalidArgumentException('Unsupported challenge type: ' . ($data['type'] ?? ''));
        }
        // mandatory data
        foreach($mandatoryData as $attribute => $property){
            if(!isset($data[$attribute])){
                throw new \InvalidArgumentException('When constructing challenge object the $data array passed in must contain "'.$attribute.'". $data provided: '.json_encode($data));
            }
            $this->$property = $data[$attribute];         
        }

        // optional data
        if(isset($data['error'])){
            $this->error = new ChallengeError($data['error']);
        }
        if(isset($data['validationRecord'])){
            $this->validationRecord = $data['validationRecord'];
        }
        if(isset($data['validated'])){
            $this->validated = $data['validated'];
        }
    }

    /**
     * Get the URL for the challenge
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Returns challenge type (DNS or HTTP)
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Returns the token
     * @return string
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Returns the status
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isValid(): bool
    {
        return $this->status === self::STATUS_VALID;
    }

    public function isInvalid(): bool
    {
        return $this->status === self::STATUS_INVALID;
    }

    /**
     * @return array<string>
     */
    public function getIssuerDomainNames(): array
    {
        return $this->issuerDomainNames;
    }

    /**
     * Returns authorization URL
     * @return string
     */
    public function getAuthorizationURL(): string
    {
        return $this->authorizationURL;
    }

    /**
     * Returns if the challenge has an error
     * @return bool
     */
    public function hasError()
    {
        return $this->getError() !== null;
    }

    /**
     * Returns the challenge error (if present)
     * @return null|ChallengeError
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Returns if the record has a validation record
     * @return bool
     */
    public function hasValidationRecord()
    {
        return $this->getValidationRecord() !== null;
    }

    /**
     * Returns the validation record (if present)
     * @return array|null
     */
    public function getValidationRecord()
    {
        return $this->validationRecord;
    }

    /**
     * Returns the validation time for the challenge
     * @return string|null
     */
    public function getValidated()
    {
        return $this->validated;
    }
}
