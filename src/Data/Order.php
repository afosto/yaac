<?php

namespace Afosto\Acme\Data;

class Order
{
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
     * @var array<array{type:string,value:string}>
     */
    protected $identifiers;

    /**
     * @var array<string>
     */
    protected $authorizations;

    /**
     * @var string
     */
    protected $finalizeURL;

    /**
     * @var array<string>
     */
    protected $domains;

    /**
     * @var string|null
     */
    protected $certificateURL;

    /**
     * Order constructor.
     * @param array<string> $domains
     * @param string $url
     * @param string $status
     * @param string $expiresAt
     * @param array<array{type:string,value:string}> $identifiers
     * @param array<string> $authorizations
     * @param string $finalizeURL
     * @param string|null $certificateURL
     * @throws \Exception
     */
    public function __construct(
        array $domains,
        string $url,
        string $status,
        string $expiresAt,
        array $identifiers,
        array $authorizations,
        string $finalizeURL,
        $certificateURL
    ) {
        //Handle the microtime date format
        if (strpos($expiresAt, '.') !== false) {
            $expiresAt = substr($expiresAt, 0, strpos($expiresAt, '.')) . 'Z';
        }
        $this->domains = $domains;
        $this->url = $url;
        $this->status = $status;

        $expiresAt = strtotime($expiresAt);

        if ($expiresAt === false) {
            throw new \InvalidArgumentException('expiresAt must be a string which works in strtotime');
        }

        $this->expiresAt = (new \DateTime())->setTimestamp($expiresAt);
        $this->identifiers = $identifiers;
        $this->authorizations = $authorizations;
        $this->finalizeURL = $finalizeURL;
        $this->certificateURL = $certificateURL;
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
     * @return array<string>
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
     * @return array<array{type:string,value:string}>
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
     * @return array<string>
     */
    public function getDomains(): array
    {
        return $this->domains;
    }

    /**
     * @return string|null
     */
    public function getCertificateURL()
    {
        return $this->certificateURL;
    }
}
