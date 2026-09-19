<?php

namespace Afosto\Acme\Data;

class Challenge
{
    public function __construct(
        protected string $authorizationURL,
        protected string $type,
        protected string $status,
        protected string $url,
        protected string $token,
    ) {
    }

    /**
     * Get the URL for the challenge
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Returns challenge type (DNS or HTTP)
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Returns the token
     */
    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Returns the status
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Returns authorization URL
     */
    public function getAuthorizationURL(): string
    {
        return $this->authorizationURL;
    }
}
