<?php

namespace Afosto\Acme\Data;

use Afosto\Acme\Client;
use Afosto\Acme\Helper;
use Psr\Http\Message\ResponseInterface;
use Afosto\Acme\Data\Challenge;

class Authorization
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_VALID = 'valid';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_DEACTIVATED = 'deactivated';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    /**
     * @var string
     */
    protected $domain;

    /**
     * @var \DateTime
     */
    protected $expires;

    /**
     * @var string
     */
    protected $status = '';

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
    public function __construct(
        ResponseInterface $response,
        $digest,
        $authorizationUrl,
        private Account $account,
        private bool $allowWildcards = false,
        private ?string $persistUntil = null
    ){
        $data = json_decode((string)$response->getBody(), true);
        $this->domain = $data['identifier']['value'] ?? '';
        $this->expires = isset($data['expires']) ? (new \DateTime())->setTimestamp(strtotime($data['expires'])) : new \DateTime();
        $this->digest = $digest;
        $this->status = $data['status'] ?? '';
        $this->challenges = [];
        foreach($data['challenges'] ?? [] as $challengeData) {
            $this->challenges[] = new Challenge($authorizationUrl, $challengeData, $this);
        }
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isValid(): bool
    {
        return $this->status === self::STATUS_VALID;
    }

    public function isInvalid(): bool
    {
        return $this->status === self::STATUS_INVALID;
    }

    public function isDeactivated(): bool
    {
        return $this->status === self::STATUS_DEACTIVATED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    /**
     * Return array of challenges
     * @return Challenge[]
     */
    public function getChallenges(): array
    {
        return $this->challenges;
    }

    public function getChallenge(string $type)
    {
        foreach ($this->getChallenges() as $challenge) {
            if ($challenge->getType() == $type) {
                return $challenge;
            }
        }

        return false;
    }

    /**
     * Return the HTTP challenge
     * @return Challenge|bool
     */
    public function getHttpChallenge()
    {
        return $this->getChallenge(Challenge::TYPE_HTTP);
    }

    /**
     * @return Challenge|bool
     */
    public function getDnsChallenge()
    {
        return $this->getChallenge(Challenge::TYPE_DNS);
    }

    /**
     * @return Challenge|bool
     */
    public function getDnsPersistChallenge()
    {
        return $this->getChallenge(Challenge::TYPE_DNS_PERSIST);
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

    public function getDnsPersistRecord()
    {
        $challenge = $this->getDnsPersistChallenge();
        if ($challenge !== false) {
            $name = '_validation-persist.' . $this->getDomain();
            $value = [];
            $issuerDomains = $challenge->getIssuerDomainNames();
            if(empty($issuerDomains)){
                throw new \InvalidArgumentException('No issuer domain names provided for DNS persist challenge.');
            }
            $value[] = $issuerDomains[0];
            $value[] = 'accounturi=' . $this->account->getAccountURL();
            if($this->allowWildcards){
                $value[] = 'policy=wildcard';
            }
            if($this->persistUntil !== null){
                $value[] = 'persistUntil=' . $this->persistUntil;
            }
            $value = implode('; ', $value);
            return new Record($name, $value);
        }

        return false;
    }
}
