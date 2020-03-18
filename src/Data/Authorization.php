<?php

namespace Afosto\Acme\Data;

use Afosto\Acme\Client;

class Authorization
{

    /**
     * @var string
     */
    protected $domain;

    /**
     * @var \DateTime
     */
    protected $expires;

    /**
     * @var Challenge[]
     */
    protected $challenges = [];

    /**
     * @var string
     */
    protected $digest;

    /**
     * Authorization constructor.
     * @param string $domain
     * @param string $expires
     * @param string $digest
     * @throws \Exception
     */
    public function __construct(string $domain, string $expires, string $digest)
    {
        $this->domain = $domain;
        $this->expires = (new \DateTime())->setTimestamp(strtotime($expires));
        $this->digest = $digest;
    }

    /**
     * Add a challenge to the authorization
     * @param Challenge $challenge
     */
    public function addChallenge(Challenge $challenge)
    {
        $this->challenges[] = $challenge;
    }

    /**
     * Return the domain that is being authorized
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }


    /**
     * Return the expiry of the authorization
     * @return \DateTime
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
     * @return Challenge|bool
     */
    public function getHttpChallenge()
    {
        foreach ($this->getChallenges() as $challenge) {
            if ($challenge->getType() == Client::VALIDATION_HTTP) {
                return $challenge;
            }
        }

        return false;
    }

    /**
     * Return File object for the given challenge
     * @return File|bool
     */
    public function getFile()
    {
        $challenge = $this->getHttpChallenge();
        if ($challenge !== false) {
            return new File($challenge->getToken(), $challenge->getToken() . '.' . $this->digest);
        }
        return false;
    }
}
