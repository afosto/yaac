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
     * Order constructor.
     * @param array $domains
     * @param string $url
     * @param string $status
     * @param string $expiresAt
     * @param array $identifiers
     * @param array $authorizations
     * @param string $finalizeURL
     * @throws \Exception
     */
    public function __construct(
        array $domains,
        string $url,
        string $status,
        string $expiresAt,
        array $identifiers,
        array $authorizations,
        string $finalizeURL
    ) {
        //Handle the microtime date format
        if (strpos($expiresAt, '.') !== false) {
            $expiresAt = substr($expiresAt, 0, strpos($expiresAt, '.')) . 'Z';
        }
        $this->domains = $domains;
        $this->url = $url;
        $this->status = $status;
        $this->expiresAt = (new \DateTime())->setTimestamp(strtotime($expiresAt));
        $this->identifiers = $identifiers;
        $this->authorizations = $authorizations;
        $this->finalizeURL = $finalizeURL;
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
        return $this->domains;
    }
}
