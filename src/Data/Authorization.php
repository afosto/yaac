<?php

namespace Afosto\Acme\Data;

use Afosto\Acme\Client;
use Afosto\Acme\Helper;

class Authorization
{
    protected \DateTime $expires;

    /**
     * @var Challenge[]
     */
    protected array $challenges = [];

    public function __construct(
        protected string $domain,
        string $expires,
        protected string $digest,
    ) {
        $expires = strtotime($expires);

        if ($expires === false) {
            throw new \InvalidArgumentException('expires must be a string which works in strtotime');
        }

        $this->expires = (new \DateTime())->setTimestamp($expires);
    }

    /**
     * Add a challenge to the authorization
     */
    public function addChallenge(Challenge $challenge): void
    {
        $this->challenges[] = $challenge;
    }

    /**
     * Return the domain that is being authorized
     */
    public function getDomain(): string
    {
        return $this->domain;
    }


    /**
     * Return the expiry of the authorization
     */
    public function getExpires(): \DateTime
    {
        return $this->expires;
    }

    /**
     * Return array of challenges
     * @return Challenge[]
     */
    public function getChallenges(): array
    {
        return $this->challenges;
    }

    /**
     * Return the HTTP challenge
     */
    public function getHttpChallenge(): Challenge|false
    {
        foreach ($this->getChallenges() as $challenge) {
            if ($challenge->getType() == Client::VALIDATION_HTTP) {
                return $challenge;
            }
        }

        return false;
    }

    /**
     * Return the DNS challenge
     */
    public function getDnsChallenge(): Challenge|false
    {
        foreach ($this->getChallenges() as $challenge) {
            if ($challenge->getType() == Client::VALIDATION_DNS) {
                return $challenge;
            }
        }

        return false;
    }

    /**
     * Return File object for the HTTP challenge
     */
    public function getFile(): File|false
    {
        $challenge = $this->getHttpChallenge();
        if ($challenge !== false) {
            return new File($challenge->getToken(), $challenge->getToken() . '.' . $this->digest);
        }

        return false;
    }

    /**
     * Returns the DNS record object
     */
    public function getTxtRecord(): Record|false
    {
        $challenge = $this->getDnsChallenge();
        if ($challenge !== false) {
            $hash = hash('sha256', $challenge->getToken() . '.' . $this->digest, true);
            $value = Helper::toSafeString($hash);
            return new Record('_acme-challenge.' . $this->getDomain(), $value);
        }

        return false;
    }
}
