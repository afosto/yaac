<?php

namespace Afosto\Acme\Data;

class Challenge extends Data
{

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
    protected $token;

    /**
     * Challenge constructor.
     * @param string $authorizationURL
     * @param string $type
     * @param string $status
     * @param string $url
     * @param string $token
     */
    public function __construct(string $authorizationURL, string $type, string $status, string $url, string $token)
    {
        $this->authorizationURL = $authorizationURL;
        $this->type = $type;
        $this->status = $status;
        $this->url = $url;
        $this->token = $token;
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

    /**
     * Returns authorization URL
     * @return string
     */
    public function getAuthorizationURL(): string
    {
        return $this->authorizationURL;
    }

    /**
     * @param string $json
     * @return Challenge
     */
    public static function fromJson($json)
    {
        $data = json_decode($json, true);

        return new Challenge(
            $data['authorizationURL'],
            $data['type'],
            $data['status'],
            $data['url'],
            $data['token']
        );
    }
}
