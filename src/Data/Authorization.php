<?php

namespace Afosto\Acme\Data;

use Afosto\Acme\Client;
use Afosto\Acme\Helper;

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
     * @var string|null
     */
    protected $accountUri;

    /**
     * @var bool
     */
    protected $isWildcard;

    /**
     * Authorization constructor.
     * @param string $domain
     * @param string $expires
     * @param string $digest
     * @param array $options
     */
    public function __construct(string $domain, string $expires, string $digest, array $options = [])
    {
        $this->domain = $domain;
        $this->expires = (new \DateTime())->setTimestamp(strtotime($expires));
        $this->digest = $digest;
        $this->accountUri = $options['accountUri'] ?? null;
        $this->isWildcard = $options['isWildcard'] ?? false;
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
     * Return the DNS persist challenge
     * @return Challenge|bool
     */
    public function getDnsPersistChallenge()
    {
        foreach ($this->getChallenges() as $challenge) {
            if ($challenge->getType() == Client::VALIDATION_DNS_PERSIST) {
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
     * Returns the DNS persist record object for dns-persist-01 validation
     *
     * @return Record|bool
     */
    public function getDnsPersistRecord()
    {
        $challenge = $this->getDnsPersistChallenge();
        if ($challenge === false) {
            return false;
        }

        $issuerDomainNames = $challenge->getIssuerDomainNames();
        if (empty($issuerDomainNames)) {
            return false;
        }

        $value = $issuerDomainNames[0] . '; accounturi=' . $this->accountUri;

        if ($this->isWildcard) {
            $value .= '; policy=wildcard';
        }

        return new Record('_validation-persist.' . $this->getDomain(), $value);
    }
}
