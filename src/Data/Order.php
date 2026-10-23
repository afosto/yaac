<?php


namespace Afosto\Acme\Data;

use Psr\Http\Message\ResponseInterface;

class Order
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_READY = 'ready';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_VALID = 'valid';
    public const STATUS_INVALID = 'invalid';

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $status;

    /**
     * @var \DateTime
     */
    protected $expiresAt;

    /**
     * @var array
     */
    protected $identifiers;

    /**
     * @var array
     */
    protected $authorizations;

    /**
     * @var string
     */
    protected $finalizeURL;

    /**
     * @var array
     */
    protected $domains;

    /**
     * @var string|null
     */
    protected $certificateURL;

    protected int $retryAfter = 0;
    
    public function __construct(
        ResponseInterface $response
    ) {

        $data = json_decode((string)$response->getBody(), true);    
        $this->domains = [];
        foreach ($data['identifiers'] as $identifier) {
            $this->domains[] = $identifier['value'];
        }

        $this->url = $response->getHeaderLine('location');
        $this->status = $data['status'] ?? '';
        $this->identifiers = $data['identifiers'] ?? [];
        $this->authorizations = $data['authorizations'] ?? [];
        $this->finalizeURL = $data['finalize'] ?? null;
        $this->certificateURL = $data['certificate'] ?? null;


        //Handle the microtime date format
        $expiresAt = $data['expiresAt'] ?? '';
        if (strpos($expiresAt, '.') !== false) {
            $expiresAt = substr($expiresAt, 0, strpos($expiresAt, '.')) . 'Z';
        }
        $this->expiresAt = (new \DateTime())->setTimestamp(strtotime($expiresAt));

        $this->retryAfter = (int) $response->getHeaderLine('Retry-After');
    }


    /**
     * Returns the order number
     * @return string
     */
    public function getId(): string
    {
        return substr($this->url, strrpos($this->url, '/') + 1);
    }

    /**
     * Returns the order URL
     * @return string
     */
    public function getURL(): string
    {
        return $this->url;
    }

    /**
     * Return set of authorizations for the order
     * @return string[]
     */
    public function getAuthorizationURLs(): array
    {
        return $this->authorizations;
    }

    /**
     * Returns order status
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

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
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
     * Returns expires at
     * @return \DateTime
     */
    public function getExpiresAt(): \DateTime
    {
        return $this->expiresAt;
    }

    /**
     * Returs domains as identifiers
     * @return array
     */
    public function getIdentifiers(): array
    {
        return $this->identifiers;
    }

    /**
     * Returns url
     * @return string
     */
    public function getFinalizeURL(): string
    {
        return $this->finalizeURL;
    }

    /**
     * Returns domains for the order
     * @return array
     */
    public function getDomains(): array
    {
        return $this->domains ?? [];
    }

    /**
     * @return string|null
     */
    public function getCertificateURL()
    {
        return $this->certificateURL;
    }

    public function hasRetryAfter(): bool
    {
        return $this->retryAfter > 0;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
