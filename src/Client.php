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
    const DIRECTORY_LIVE = 'https://acme-v02.api.letsencrypt.org/directory';

    /**
     * Staging url
     */
    const DIRECTORY_STAGING = 'https://acme-staging-v02.api.letsencrypt.org/directory';

    /**
     * Flag for production
     */
    const MODE_LIVE = 'live';

    /**
     * Flag for staging
     */
    const MODE_STAGING = 'staging';

    /**
     * New account directory
     */
    const DIRECTORY_NEW_ACCOUNT = 'newAccount';

    /**
     * Nonce directory
     */
    const DIRECTORY_NEW_NONCE = 'newNonce';

    /**
     * Order certificate directory
     */
    const DIRECTORY_NEW_ORDER = 'newOrder';

    public const CONFIG_USERNAME = 'username';
    public const CONFIG_COUNTRY_NAME = 'country_name';
    public const CONFIG_ACCOUNT_KEY = 'account_key';
    public const CONFIG_FS = 'fs';
    public const CONFIG_MODE = 'mode';
    public const CONFIG_ALLOW_WILDCARDS = 'allow_wildcards';
    public const CONFIG_PERSIST_UNTIL = 'persist_until';

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
     * @var string
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
        if ($this->getOption(self::CONFIG_FS, false)) {
            $this->filesystem = $this->getOption(self::CONFIG_FS);
        } else {
            if($this->getOption(self::CONFIG_ACCOUNT_KEY)){
                $this->accountKey = $this->getOption(self::CONFIG_ACCOUNT_KEY);
            }else{
                throw new \LogicException(sprintf('No filesystem (%s) or account key (%s) option supplied', self::CONFIG_FS, self::CONFIG_ACCOUNT_KEY));
            }
        }

        if ($this->getOption('username', false) === false) {
            throw new \LogicException('Username not provided');
        }

        $this->init();
    }

    /**
     * Get an existing order by ID
     *
     * @param $id
     * @return Order
     * @throws \Exception
     */
    public function getOrder($id): Order
    {
        $url = str_replace('new-order', 'order', $this->getUrl(self::DIRECTORY_NEW_ORDER));
        $url = $url . '/' . $this->getAccount()->getId() . '/' . $id;

        return $this->orderFromRequest(
            $url,
            $this->signPayloadKid(
                null,
                $url
            )
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

        return $this->orderFromRequest(
            $url,
            $this->signPayloadKid(
                [
                    'identifiers' => $identifiers,
                ],
                $url
            )
        );
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
            $authorizations[] = new Authorization(
                $this->request(
                    $authorizationURL,
                    $this->signPayloadKid(null, $authorizationURL)
                ),
                $this->getDigest(),
                $authorizationURL,
                $this->getAccount(),
                $this->getOption(self::CONFIG_ALLOW_WILDCARDS, false),
                $this->getOption(self::CONFIG_PERSIST_UNTIL, null)
            );
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
    public function selfTest(
        Authorization $authorization, 
        $type = Challenge::TYPE_HTTP, 
        $maxAttempts = 15
    ): bool {
        if ($type == Challenge::TYPE_HTTP) {
            return $this->selfHttpTest($authorization, $maxAttempts);
        } elseif ($type == Challenge::TYPE_DNS) {
            return $this->selfDNSTest($authorization, $maxAttempts);
        } elseif ($type == Challenge::TYPE_DNS_PERSIST) {
            return $this->selfDnsPersistTest($authorization, $maxAttempts);
        }
        throw new \Exception('Unknown challenge type: ' . $type);
    }

    public function selfTestChallenge(Challenge $challenge, $maxAttempts = 15): bool
    {
        return $this->selfTest(
            $challenge->getAuthorization(),
            $challenge->getType(),
            $maxAttempts
        );
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
                sleep(ceil(15 / $maxAttempts));
            }
            $maxAttempts--;
        } while ($maxAttempts > 0 && $data['status'] != 'valid');

        return (isset($data['status']) && $data['status'] == 'valid');
    }

    public function generatePrivateKey(): string
    {
        return Helper::getNewKey($this->getOption('key_length', 4096));
    }

    public function generateCsr(Order $order, $privateKey): string
    {
        return  Helper::getCsr($order->getDomains(), $privateKey, $this->getOption('country_name', 'NL'));
    }

    public function finalize(Order $order, $csr): Order
    {
        $der = Helper::toDer($csr);
        return new Order(
            $this->request(
                $order->getFinalizeURL(),
                $this->signPayloadKid(
                    ['csr' => Helper::toSafeString($der)],
                    $order->getFinalizeURL()
                )
            )
        );
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
        return new Certificate(
            $this->request(
                $order->getCertificateURL(),
                $this->signPayloadKid(null, $order->getCertificateURL())
            )
        );
    }

    /**
     * Return LE account information
     *
     * @return Account
     * @throws \Exception
     */
    public function getAccount(): Account
    {
        if(!$this->account){
            $response = $this->request(
                $this->getUrl(self::DIRECTORY_NEW_ACCOUNT),
                $this->signPayloadJWK(
                    [
                        'onlyReturnExisting' => true,
                    ],
                    $this->getUrl(self::DIRECTORY_NEW_ACCOUNT)
                )
            );
            $this->account = new Account($response);
        }
        return $this->account;
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
     * @param $maxAttempts
     * @return bool
     */
    protected function selfHttpTest(Authorization $authorization, $maxAttempts)
    {
        do {
            $maxAttempts--;
            try {
                $response = $this->getSelfTestClient()->request(
                    'GET',
                    'http://' . $authorization->getDomain() . '/.well-known/acme-challenge/' .
                    $authorization->getFile()->getFilename()
                );
                $contents = (string)$response->getBody();
                if ($contents == $authorization->getFile()->getContents()) {
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
     * @param $maxAttempts
     * @return bool
     */
    protected function selfDNSTest(Authorization $authorization, $maxAttempts)
    {
        do {
            $response = $this->getSelfTestDNSClient()->get(
                '/dns-query',
                [
                    'query' => [
                        'name' => $authorization->getTxtRecord()->getName(),
                        'type' => 'TXT'
                    ]
                ]
            );
            $data = json_decode((string)$response->getBody(), true);
            if (isset($data['Answer'])) {
                foreach ($data['Answer'] as $result) {
                    if (trim($result['data'], "\"") == $authorization->getTxtRecord()->getValue()) {
                        return true;
                    }
                }
            }
            if ($maxAttempts > 1) {
                sleep(ceil(45 / $maxAttempts));
            }
            $maxAttempts--;
        } while ($maxAttempts > 0);

        return false;
    }

    /**
     * Self DNS test client that uses Cloudflare's DNS API
     * @param Authorization $authorization
     * @param $maxAttempts
     * @return bool
     */
    protected function selfDnsPersistTest(Authorization $authorization, $maxAttempts)
    {
        do {
            $dnsRecord = $authorization->getDnsPersistRecord(); 
            $response = $this->getSelfTestDNSClient()->get(
                '/dns-query',
                [
                    'query' => [
                        'name' => $dnsRecord->getName(),
                        'type' => 'TXT'
                    ]
                ]
            );
            $data = json_decode((string)$response->getBody(), true);

            if (isset($data['Answer'])) {
                foreach ($data['Answer'] as $result) {
                    if (strpos(trim($result['data'], "\""), $dnsRecord->getValue()) == 0) {
                        return true;
                    }
                }
            }
            if ($maxAttempts > 1) {
                sleep(ceil(45 / $maxAttempts));
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
    protected function init()
    {
        //Load the directories from the LE api
        $response = $this->getHttpClient()->get('/directory');
        $result = \GuzzleHttp\json_decode((string)$response->getBody(), true);
        $this->directories = $result;

        //Prepare LE account
        $this->loadKeys();
        $this->tosAgree();
        $this->account = $this->getAccount();
    }

    protected function loadKeys()
    {
        if($this->accountKey){
            // validate supplied account key
            $this->accountKey = openssl_pkey_get_private($this->accountKey);
            $this->privateKeyDetails = openssl_pkey_get_details($this->accountKey);
        }else{
            if ($this->getFilesystem()->has($this->getPath('account.pem')) === false) {
                $this->getFilesystem()->write(
                    $this->getPath('account.pem'),
                    Helper::getNewKey($this->getOption('key_length', 4096))
                );
            }
            $privateKey = $this->getFilesystem()->read($this->getPath('account.pem'));
            $this->accountKey = openssl_pkey_get_private($privateKey);
            $this->privateKeyDetails = openssl_pkey_get_details($this->accountKey);
        }
        //Make sure a private key is in place
        if(!$this->privateKeyDetails){
            throw new \LogicException('No valid private key found');
        }
    }

    /**
     * Agree to the terms of service
     *
     * @throws \Exception
     */
    protected function tosAgree()
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
     *
     * @param null $path
     * @return string
     */
    protected function getPath($path = null): string
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

    protected function hasFileSystem(): bool
    {
        return $this->getFilesystem() !== null;
    }

    /**
     * Get a defined option
     *
     * @param      $key
     * @param null $default
     *
     * @return mixed|null
     */
    protected function getOption($key, $default = null)
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
            $this->digest = Helper::toSafeString(hash('sha256', json_encode($this->getJWKHeader()), true));
        }

        return $this->digest;
    }

    /**
     * Send a request to the LE API
     *
     * @param $url
     * @param array $payload
     * @param string $method
     * @return ResponseInterface
     */
    public function request($url, $payload = [], $method = 'POST'): ResponseInterface
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
     * @param $url
     * @param array $payload
     * @param string $method
     */
    protected function orderFromRequest($url, $payload = [], $method = 'POST'): Order
    {
        return new Order(
            $this->request($url, $payload, $method)
        );
    }

    /**
     * Get the LE directory path
     *
     * @param $directory
     *
     * @return mixed
     * @throws \Exception
     */
    protected function getUrl($directory): string
    {
        if (isset($this->directories[$directory])) {
            return $this->directories[$directory];
        }

        throw new \Exception('Invalid directory: ' . $directory . ' not listed');
    }


    /**
     * Get the key
     *
     * @return bool|resource|string
     * @throws \Exception
     */
    protected function getAccountKey()
    {
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
     * @param $url
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
     * @param $url
     * @param $kid
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
     * @param $payload
     * @param $url
     * @return array
     * @throws \Exception
     */
    public function signPayloadJWK($payload, $url): array
    {
        $payload = is_array($payload) ? str_replace('\\/', '/', json_encode($payload)) : '';
        $payload = Helper::toSafeString($payload);
        $protected = Helper::toSafeString(json_encode($this->getJWK($url)));

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
     * @param $payload
     * @param $url
     * @return array
     * @throws \Exception
     */
    public function signPayloadKid($payload, $url): array
    {
        $payload = is_array($payload) ? str_replace('\\/', '/', json_encode($payload)) : '';
        $payload = Helper::toSafeString($payload);
        $protected = Helper::toSafeString(json_encode($this->getKID($url)));

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
