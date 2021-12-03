<?php

namespace Afosto\Acme\Data;

use Afosto\Acme\Client;
use Afosto\Acme\Helper;

class Authorization extends Data
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
     * @return Challenge|bool
     */
    public function getDnsChallenge()
    {
        foreach ($this->getChallenges() as $challenge) {
            if ($challenge->getType() == Client::VALIDATION_DNS) {
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

    /**
     * Returns the DNS record object
     *
     * @param Challenge $challenge
     * @return Record|bool
     */
    public function getTxtRecord()
    {
        $challenge = $this->getDnsChallenge();
        if ($challenge !== false) {
            $hash = hash('sha256', $challenge->getToken() . '.' . $this->digest, true);
            $value = Helper::toSafeString($hash);
            return new Record('_acme-challenge.' . $this->getDomain(), $value);
        }

        return false;
    }

    /**
     * @param string $json
     * @return Authorization
     * @throws \Exception
     */
    public static function fromJson($json)
    {
        $data = json_decode($json, true);

        $authorization = new Authorization($data['domain'], $data['expires']['date'], $data['digest']);

        // add challenges
        foreach ($data['challenges'] as $challenge) {
            $authorization->addChallenge(Challenge::fromJson(json_encode($challenge)));
        }

        return $authorization;
    }
}
