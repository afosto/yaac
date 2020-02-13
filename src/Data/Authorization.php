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

    public function __construct(string $domain, string $expires, string $digest)
    {
        $this->domain = $domain;
        $this->expires = (new \DateTime())->setTimestamp(strtotime($expires));
        $this->digest = $digest;
    }

    public function addChallenge(Challenge $challenge)
    {
        $this->challenges[] = $challenge;
    }

    /**
     * @return array
     */
    public function getDomain(): string
    {
        return $this->domain;
    }


    /**
     * @return \DateTime
     */
    public function getExpires(): \DateTime
    {
        return $this->expires;
    }

    /**
     * @return Challenge[]
     */
    public function getChallenges(): array
    {
        return $this->challenges;
    }

    /**
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
     * @param Challenge $challenge
     * @return File|bool
     */
    public function getFile(Challenge $challenge)
    {
        if ($challenge->getType() == Client::VALIDATION_HTTP) {
            $file = new File($challenge->getToken(), $challenge->getToken() . '.' . $this->digest);
            return $file;
        }
        return false;
    }
}
