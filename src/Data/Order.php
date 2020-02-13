<?php


namespace Afosto\LetsEncrypt\Data;

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


    public function __construct(
        array $domains,
        string $url,
        string $status,
        string $expiresAt,
        array $identifiers,
        array $authorizations,
        string $finalizeURL
    ) {
        $this->domains = $domains;
        $this->url = $url;
        $this->status = $status;
        $this->expiresAt = (new \DateTime())->setTimestamp(strtotime($expiresAt));
        $this->identifiers = $identifiers;
        $this->authorizations = $authorizations;
        $this->finalizeURL = $finalizeURL;
    }

    public function getId(): string
    {
        return substr($this->url, strrpos($this->url, '/') + 1);
    }

    public function getURL(): string
    {
        return $this->url;
    }

    public function getAuthorizationURLs(): array
    {
        return $this->authorizations;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getExpiresAt(): \DateTime
    {
        return $this->expiresAt;
    }

    public function getIdentifiers(): array
    {
        return $this->identifiers;
    }

    public function getFinalizeURL(): string
    {
        return $this->finalizeURL;
    }

    public function getDomains(): array
    {
        return $this->domains;
    }
}
