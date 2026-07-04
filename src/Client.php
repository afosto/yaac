<?php

namespace Afosto\Acme;

use Afosto\Acme\Data\Account;
use Afosto\Acme\Data\Authorization;
use Afosto\Acme\Data\Certificate;
use Afosto\Acme\Data\Challenge;
use Afosto\Acme\Data\Order;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use League\Flysystem\Filesystem;
use Psr\Http\Message\ResponseInterface;

class Client
{
    /**
     * Live url
     */
    public const DIRECTORY_LIVE = 'https://acme-v02.api.letsencrypt.org/directory';

    /**
     * Staging url
     */
    public const DIRECTORY_STAGING = 'https://acme-staging-v02.api.letsencrypt.org/directory';

    /**
     * Flag for production
     */
    public const MODE_LIVE = 'live';

    /**
     * Flag for staging
     */
    public const MODE_STAGING = 'staging';

    /**
     * New account directory
     */
    public const DIRECTORY_NEW_ACCOUNT = 'newAccount';

    /**
     * Nonce directory
     */
    public const DIRECTORY_NEW_NONCE = 'newNonce';

    /**
     * Order certificate directory
     */
    public const DIRECTORY_NEW_ORDER = 'newOrder';

    /**
     * Http validation
     */
    public const VALIDATION_HTTP = 'http-01';

    /**
     * DNS validation
     */
    public const VALIDATION_DNS = 'dns-01';

    /**
     * @var string
     */
    protected $nonce;

    /**
     * @var Account
     */
    protected $account;

    /**
     * @var array
     */
    protected $privateKeyDetails;

    /**
     * @var \OpenSSLAsymmetricKey
     */
    protected $accountKey;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var array
     */
    protected $directories = [];

    /**
     * @var array
     */
    protected $header = [];

    /**
     * @var string
     */
    protected $digest;

    /**
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * @var array
     */
    protected $config;

    /**
     * Client constructor.
     *
     * @param array $config
     *
     * @type string $mode The mode for ACME (production / staging)
     * @type Filesystem $fs Filesystem for storage of static data
     * @type string $basePath The base path for the filesystem (used to store account information and csr / keys
     * @type string $username The acme username
     * @type string $source_ip The source IP for Guzzle (via curl.options) to bind to (defaults to 0.0.0.0 [OS default])
     * }
     */
    public function __construct($config = [])
    {
        $this->config = $config;
        if ($this->getOption('fs', false)) {
            $this->filesystem = $this->getOption('fs');
        } else {
            throw new \LogicException('No filesystem option supplied');
        }

        if ($this->getOption('username', false) === false) {
            throw new \LogicException('Username not provided');
        }

        $this->init();
    }

    /**
     * Get an existing order by ID
     *
     * @param string|int $id
     * @return Order
     * @throws \Exception
     */
    public function getOrder($id): Order
    {
        $url = str_replace('new-order', 'order', $this->getUrl(self::DIRECTORY_NEW_ORDER));
        $url = $url . '/' . $this->getAccount()->getId() . '/' . $id;
        $response = $this->request($url, $this->signPayloadKid(null, $url));
        $data = json_decode((string)$response->getBody(), true);

        $domains = [];
        foreach ($data['identifiers'] as $identifier) {
            $domains[] = $identifier['value'];
        }

        return new Order(
            $domains,
            $url,
            $data['status'],
            $data['expires'],
            $data['identifiers'],
            $data['authorizations'],
            $data['finalize']
        );
    }

    /**
     * Get ready status for order
     *
     * @param Order $order
     * @return bool
     * @throws \Exception
     */
    public function isReady(Order $order): bool
    {
        $order = $this->getOrder($order->getId());
        return $order->getStatus() == 'ready';
    }


    /**
     * Create a new order
     *
     * @param array $domains
     * @return Order
     * @throws \Exception
     */
    public function createOrder(array $domains): Order
    {
        $identifiers = [];
        foreach ($domains as $domain) {
            $identifiers[] =
                [
                    'type'  => 'dns',
                    'value' => $domain,
                ];
        }

        $url = $this->getUrl(self::DIRECTORY_NEW_ORDER);
        $response = $this->request($url, $this->signPayloadKid(
            [
                'identifiers' => $identifiers,
            ],
            $url
        ));

        $data = json_decode((string)$response->getBody(), true);
        $order = new Order(
            $domains,
            $response->getHeaderLine('location'),
            $data['status'],
            $data['expires'],
            $data['identifiers'],
            $data['authorizations'],
            $data['finalize']
        );


        return $order;
    }

    /**
     * Obtain authorizations
     *
     * @param Order $order
     * @return array|Authorization[]
     * @throws \Exception
     */
    public function authorize(Order $order): array
    {
        $authorizations = [];
        foreach ($order->getAuthorizationURLs() as $authorizationURL) {
            $response = $this->request(
                $authorizationURL,
                $this->signPayloadKid(null, $authorizationURL)
            );
            $data = json_decode((string)$response->getBody(), true);
            $authorization = new Authorization($data['identifier']['value'], $data['expires'], $this->getDigest());

            foreach ($data['challenges'] as $challengeData) {
                $challenge = new Challenge(
                    $authorizationURL,
                    $challengeData['type'],
                    $challengeData['status'],
                    $challengeData['url'],
                    $challengeData['token']
                );
                $authorization->addChallenge($challenge);
            }
            $authorizations[] = $authorization;
        }

        return $authorizations;
    }

    /**
     * Run a self-test for the authorization
     * @param Authorization $authorization
     * @param string $type
     * @param int $maxAttempts
     * @return bool
     */
    public function selfTest(Authorization $authorization, $type = self::VALIDATION_HTTP, $maxAttempts = 15): bool
    {
        if ($type == self::VALIDATION_HTTP) {
            return $this->selfHttpTest($authorization, $maxAttempts);
        } elseif ($type == self::VALIDATION_DNS) {
            return $this->selfDNSTest($authorization, $maxAttempts);
        }
        return false;
    }

    /**
     * Validate a challenge
     *
     * @param Challenge $challenge
     * @param int $maxAttempts
     * @return bool
     * @throws \Exception
     */
    public function validate(Challenge $challenge, int $maxAttempts = 15): bool
    {
        $this->request(
            $challenge->getUrl(),
            $this->signPayloadKid([
                'keyAuthorization' => $challenge->getToken() . '.' . $this->getDigest()
            ], $challenge->getUrl())
        );

        $data = [];
        do {
            $response = $this->request(
                $challenge->getAuthorizationURL(),
                $this->signPayloadKid(null, $challenge->getAuthorizationURL())
            );
            $data = json_decode((string)$response->getBody(), true);
            if ($maxAttempts > 1 && $data['status'] != 'valid') {
                sleep((int) ceil(15 / $maxAttempts));
            }
            $maxAttempts--;
        } while ($maxAttempts > 0 && $data['status'] != 'valid');

        return (isset($data['status']) && $data['status'] == 'valid');
    }

    /**
     * Return a certificate
     *
     * @param Order $order
     * @return Certificate
     * @throws \Exception
     */
    public function getCertificate(Order $order): Certificate
    {
        $privateKey = Helper::getNewKey($this->getOption('key_length', 4096));
        $csr = Helper::getCsr($order->getDomains(), $privateKey);
        $der = Helper::toDer($csr);

        $response = $this->request(
            $order->getFinalizeURL(),
            $this->signPayloadKid(
                ['csr' => Helper::toSafeString($der)],
                $order->getFinalizeURL()
            )
        );

        $data = json_decode((string)$response->getBody(), true);
        $certificateResponse = $this->request(
            $data['certificate'],
            $this->signPayloadKid(null, $data['certificate'])
        );
        $chain = $str = preg_replace('/^[ \t]*[\r\n]+/m', '', (string)$certificateResponse->getBody());
        return new Certificate($privateKey, $csr, $chain);
    }


    /**
     * Return LE account information
     *
     * @return Account
     * @throws \Exception
     */
    public function getAccount(): Account
    {
        $response = $this->request(
            $this->getUrl(self::DIRECTORY_NEW_ACCOUNT),
            $this->signPayloadJWK(
                [
                    'onlyReturnExisting' => true,
                ],
                $this->getUrl(self::DIRECTORY_NEW_ACCOUNT)
            )
        );

        $data = json_decode((string)$response->getBody(), true);
        $accountURL = $response->getHeaderLine('Location');
        $date = (new \DateTime())->setTimestamp(strtotime($data['createdAt']));
        return new Account($date, ($data['status'] == 'valid'), $accountURL);
    }

    /**
     * Returns the ACME api configured Guzzle Client
     * @return HttpClient
     */
    protected function getHttpClient()
    {
        if ($this->httpClient === null) {
            $config = [
                'base_uri' => (
                ($this->getOption('mode', self::MODE_LIVE) == self::MODE_LIVE) ?
                    self::DIRECTORY_LIVE : self::DIRECTORY_STAGING),
            ];
            if ($this->getOption('source_ip', false) !== false) {
                $config['curl.options']['CURLOPT_INTERFACE'] = $this->getOption('source_ip');
            }
            $this->httpClient = new HttpClient($config);
        }
        return $this->httpClient;
    }

    /**
     * Returns a Guzzle Client configured for self test
     * @return HttpClient
     */
    protected function getSelfTestClient()
    {
        return new HttpClient([
            'verify'          => false,
            'timeout'         => 10,
            'connect_timeout' => 3,
            'allow_redirects' => true,
        ]);
    }

    /**
     * Self HTTP test
     * @param Authorization $authorization
     * @param int $maxAttempts
     * @return bool
     */
    protected function selfHttpTest(Authorization $authorization, $maxAttempts)
    {
        do {
            $maxAttempts--;

            $file = $authorization->getFile();

            if ($file === false) {
                throw new \RuntimeException('Could not get HTTP challenge file');
            }

            try {
                $response = $this->getSelfTestClient()->request(
                    'GET',
                    'http://' . $authorization->getDomain() . '/.well-known/acme-challenge/' .
                    $file->getFilename()
                );
                $contents = (string)$response->getBody();
                if ($contents == $file->getContents()) {
                    return true;
                }
            } catch (RequestException $e) {
            }
        } while ($maxAttempts > 0);

        return false;
    }

    /**
     * Self DNS test client that uses Cloudflare's DNS API
     * @param Authorization $authorization
     * @param int $maxAttempts
     * @return bool
     */
    protected function selfDNSTest(Authorization $authorization, $maxAttempts)
    {
        do {
            $txtRecord = $authorization->getTxtRecord();

            if ($txtRecord === false) {
                throw new \RuntimeException('Could not get DNS challenge record');
            }

            $response = $this->getSelfTestDNSClient()->get(
                '/dns-query',
                [
                    'query' => [
                        'name' => $txtRecord->getName(),
                        'type' => 'TXT'
                    ]
                ]
            );
            $data = json_decode((string)$response->getBody(), true);
            if (isset($data['Answer'])) {
                foreach ($data['Answer'] as $result) {
                    if (trim($result['data'], "\"") == $txtRecord->getValue()) {
                        return true;
                    }
                }
            }
            if ($maxAttempts > 1) {
                sleep((int) ceil(45 / $maxAttempts));
            }
            $maxAttempts--;
        } while ($maxAttempts > 0);

        return false;
    }

    /**
     * Return the preconfigured client to call Cloudflare's DNS API
     * @return HttpClient
     */
    protected function getSelfTestDNSClient()
    {
        return new HttpClient([
            'base_uri'        => 'https://cloudflare-dns.com',
            'connect_timeout' => 10,
            'headers'         => [
                'Accept' => 'application/dns-json',
            ],
        ]);
    }

    /**
     * Initialize the client
     */
    protected function init(): void
    {
        //Load the directories from the LE api
        $response = $this->getHttpClient()->get('/directory');
        $result = json_decode((string) $response->getBody(), true);

        if (\JSON_ERROR_NONE !== \json_last_error()) {
            throw new \RuntimeException('Lets Encrypt directories did not return an array: ' . \json_last_error_msg());
        }

        if (! is_array($result)) {
            throw new \RuntimeException('Lets Encrypt directories did not return an array');
        }

        $this->directories = $result;

        //Prepare LE account
        $this->loadKeys();
        $this->tosAgree();
        $this->account = $this->getAccount();
    }

    protected function loadKeys(): void
    {
        //Make sure a private key is in place
        if ($this->getFilesystem()->has($this->getPath('account.pem')) === false) {
            $this->getFilesystem()->write(
                $this->getPath('account.pem'),
                Helper::getNewKey($this->getOption('key_length', 4096))
            );
        }
        $privateKey = $this->getFilesystem()->read($this->getPath('account.pem'));
        $privateKey = openssl_pkey_get_private($privateKey);

        if ($privateKey === false) {
            throw new \Exception('Private key was false somehow');
        }

        $privateKeyDetails = openssl_pkey_get_details($privateKey);

        if ($privateKeyDetails === false) {
            throw new \Exception('Private key details was false somehow');
        }

        $this->privateKeyDetails = $privateKeyDetails;
    }

    /**
     * Agree to the terms of service
     *
     * @throws \Exception
     */
    protected function tosAgree(): void
    {
        $this->request(
            $this->getUrl(self::DIRECTORY_NEW_ACCOUNT),
            $this->signPayloadJWK(
                [
                    'contact'              => [
                        'mailto:' . $this->getOption('username'),
                    ],
                    'termsOfServiceAgreed' => true,
                ],
                $this->getUrl(self::DIRECTORY_NEW_ACCOUNT)
            )
        );
    }

    /**
     * Get a formatted path
     */
    protected function getPath(?string $path = null): string
    {
        $userDirectory = preg_replace('/[^a-z0-9]+/', '-', strtolower($this->getOption('username')));

        return $this->getOption(
            'basePath',
            'le'
        ) . DIRECTORY_SEPARATOR . $userDirectory . ($path === null ? '' : DIRECTORY_SEPARATOR . $path);
    }

    /**
     * Return the Flysystem filesystem
     * @return Filesystem
     */
    protected function getFilesystem(): Filesystem
    {
        return $this->filesystem;
    }

    /**
     * Get a defined option
     *
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    protected function getOption(string $key, $default = null)
    {
        if (isset($this->config[$key])) {
            return $this->config[$key];
        }

        return $default;
    }

    /**
     * Get key fingerprint
     *
     * @return string
     * @throws \Exception
     */
    protected function getDigest(): string
    {
        if ($this->digest === null) {
            $jwkHeader = json_encode($this->getJWKHeader());

            if ($jwkHeader === false) {
                throw new \Exception('JWK header could not be encoded to JSON somehow');
            }

            $this->digest = Helper::toSafeString(hash('sha256', $jwkHeader, true));
        }

        return $this->digest;
    }

    /**
     * Send a request to the LE API
     *
     * @param string $url
     * @param array $payload
     * @param string $method
     * @return ResponseInterface
     */
    protected function request($url, $payload = [], $method = 'POST'): ResponseInterface
    {
        try {
            $response = $this->getHttpClient()->request($method, $url, [
                'json'    => $payload,
                'headers' => [
                    'Content-Type' => 'application/jose+json',
                ]
            ]);
            $this->nonce = $response->getHeaderLine('replay-nonce');
        } catch (ClientException $e) {
            throw $e;
        }

        return $response;
    }

    /**
     * Get the LE directory path
     *
     * @throws \Exception
     */
    protected function getUrl(string $directory): string
    {
        if (isset($this->directories[$directory])) {
            return $this->directories[$directory];
        }

        throw new \Exception('Invalid directory: ' . $directory . ' not listed');
    }


    /**
     * Get the key
     *
     * @return \OpenSSLAsymmetricKey
     * @throws \Exception
     */
    protected function getAccountKey()
    {
        if ($this->accountKey === null) {
            $accountKey = openssl_pkey_get_private($this->getFilesystem()
                ->read($this->getPath('account.pem')));

            if ($accountKey === false) {
                throw new \Exception('Invalid account key');
            }

            $this->accountKey = $accountKey;
        }

        return $this->accountKey;
    }

    /**
     * Get the header
     *
     * @return array
     * @throws \Exception
     */
    protected function getJWKHeader(): array
    {
        return [
            'e'   => Helper::toSafeString(Helper::getKeyDetails($this->getAccountKey())['rsa']['e']),
            'kty' => 'RSA',
            'n'   => Helper::toSafeString(Helper::getKeyDetails($this->getAccountKey())['rsa']['n']),
        ];
    }

    /**
     * Get JWK envelope
     *
     * @param string $url
     * @return array
     * @throws \Exception
     */
    protected function getJWK($url): array
    {
        //Require a nonce to be available
        if ($this->nonce === null) {
            $response = $this->getHttpClient()->head($this->directories[self::DIRECTORY_NEW_NONCE]);
            $this->nonce = $response->getHeaderLine('replay-nonce');
        }
        return [
            'alg'   => 'RS256',
            'jwk'   => $this->getJWKHeader(),
            'nonce' => $this->nonce,
            'url'   => $url
        ];
    }

    /**
     * Get KID envelope
     *
     * @param string $url
     * @return array
     */
    protected function getKID($url): array
    {
        $response = $this->getHttpClient()->head($this->directories[self::DIRECTORY_NEW_NONCE]);
        $nonce = $response->getHeaderLine('replay-nonce');

        return [
            "alg"   => "RS256",
            "kid"   => $this->account->getAccountURL(),
            "nonce" => $nonce,
            "url"   => $url
        ];
    }

    /**
     * Transform the payload to the JWS format
     *
     * @param ?array $payload
     * @param string $url
     * @return array
     * @throws \Exception
     */
    protected function signPayloadJWK($payload, $url): array
    {
        if (is_array($payload)) {
            $payload = json_encode($payload);

            if ($payload === false) {
                throw new \InvalidArgumentException('payload could not be encoded to JSON, is it correct?');
            }

            $payload = str_replace('\\/', '/', $payload);
        } else {
            $payload = '';
        }

        $payload = Helper::toSafeString($payload);

        $jwk = json_encode($this->getJWK($url));

        if ($jwk === false) {
            throw new \InvalidArgumentException('JWK could not be encoded to JSON, is the url correct?');
        }

        $protected = Helper::toSafeString($jwk);

        $result = openssl_sign($protected . '.' . $payload, $signature, $this->getAccountKey(), "SHA256");

        if ($result === false) {
            throw new \Exception('Could not sign');
        }

        return [
            'protected' => $protected,
            'payload'   => $payload,
            'signature' => Helper::toSafeString($signature),
        ];
    }

    /**
     * Transform the payload to the KID format
     *
     * @param ?array $payload
     * @param string $url
     * @return array
     * @throws \Exception
     */
    protected function signPayloadKid($payload, $url): array
    {
        if (is_array($payload)) {
            $payload = json_encode($payload);

            if ($payload === false) {
                throw new \InvalidArgumentException('payload could not be encoded to JSON, is it correct?');
            }

            $payload = str_replace('\\/', '/', $payload);
        } else {
            $payload = '';
        }

        $payload = Helper::toSafeString($payload);

        $kid = json_encode($this->getKID($url));

        if ($kid === false) {
            throw new \InvalidArgumentException('KID could not be encoded to JSON, is the url correct?');
        }

        $protected = Helper::toSafeString($kid);

        $result = openssl_sign($protected . '.' . $payload, $signature, $this->getAccountKey(), "SHA256");
        if ($result === false) {
            throw new \Exception('Could not sign');
        }

        return [
            'protected' => $protected,
            'payload'   => $payload,
            'signature' => Helper::toSafeString($signature),
        ];
    }
}
