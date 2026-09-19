<?php

namespace Afosto\Acme\Data;

class Order
{
    protected \DateTime $expiresAt;

    /**
     * @param array<string> $domains
     * @param string $url
     * @param string $status
     * @param string $expiresAt
     * @param array<array{type:string,value:string}> $identifiers
     * @param array<string> $authorizations
     * @param string $finalizeURL
     * @param ?string $certificateURL
     */
    public function __construct(
        protected array $domains,
        protected string $url,
        protected string $status,
        string $expiresAt,
        protected array $identifiers,
        protected array $authorizations,
        protected string $finalizeURL,
        protected ?string $certificateURL,
    ) {
        //Handle the microtime date format
        if (strpos($expiresAt, '.') !== false) {
            $expiresAt = substr($expiresAt, 0, strpos($expiresAt, '.')) . 'Z';
        }

        $expiresAt = strtotime($expiresAt);

        if ($expiresAt === false) {
            throw new \InvalidArgumentException('expiresAt must be a string which works in strtotime');
        }

        $this->expiresAt = (new \DateTime())->setTimestamp($expiresAt);
    }


    /**
     * Returns the order number
     */
    public function getId(): string
    {
        return substr($this->url, strrpos($this->url, '/') + 1);
    }

    /**
     * Returns the order URL
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
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Returns expires at
     */
    public function getExpiresAt(): \DateTime
    {
        return $this->expiresAt;
    }

    /**
     * Returns domains as identifiers
     *
     * @return array<array{type:string,value:string}>
     */
    public function getIdentifiers(): array
    {
        return $this->identifiers;
    }

    /**
     * Returns finalize url
     */
    public function getFinalizeURL(): string
    {
        return $this->finalizeURL;
    }

    /**
     * Returns domains for the order
     *
     * @return string[]
     */
    public function getDomains(): array
    {
        return $this->domains;
    }

    public function getCertificateURL(): ?string
    {
        return $this->certificateURL;
    }
}
