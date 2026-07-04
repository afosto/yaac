<?php

namespace Afosto\Acme;

use GuzzleHttp\Exception\ClientException;

/**
 * Class Helper
 * This class contains helper methods for certificate handling
 * @package Afosto\Acme
 */
class Helper
{
    /**
     * Formatter
     * @param string $pem
     * @return string
     * @see https://eidson.info/post/php_eol_is_broken
     */
    public static function toDer($pem)
    {
        $lines = preg_split('/\n|\r\n?/', $pem);

        if ($lines === false) {
            throw new \InvalidArgumentException('Could not split PEM for some reason');
        }

        $lines = array_slice($lines, 1, -1);

        return base64_decode(implode('', $lines));
    }

    /**
     * Return certificate expiry date
     *
     * @param string $certificate
     *
     * @return \DateTime
     * @throws \Exception
     */
    public static function getCertExpiryDate($certificate): \DateTime
    {
        $info = openssl_x509_parse($certificate);
        if ($info === false) {
            throw new \Exception('Could not parse certificate');
        }
        $dateTime = new \DateTime();
        $dateTime->setTimestamp($info['validTo_time_t']);

        return $dateTime;
    }

    /**
     * Get a new key
     *
     * @return string
     */
    public static function getNewKey(int $keyLength): string
    {
        $key = openssl_pkey_new([
            'private_key_bits' => $keyLength,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($key === false) {
            throw new \Exception('Could not create a key');
        }

        openssl_pkey_export($key, $pem);

        return $pem;
    }

    /**
     * Get a new CSR
     *
     * @param array $domains
     * @param \OpenSSLAsymmetricKey|string $key
     *
     * @return string
     * @throws \Exception
     */
    public static function getCsr(array $domains, $key): string
    {
        $primaryDomain = current(($domains));
        $config = [
            '[req]',
            'distinguished_name=req_distinguished_name',
            '[req_distinguished_name]',
            '[v3_req]',
            '[v3_ca]',
            '[SAN]',
            'subjectAltName=' . implode(',', array_map(function ($domain) {
                return 'DNS:' . $domain;
            }, $domains)),
        ];

        $fn = tempnam(sys_get_temp_dir(), md5((string) microtime(true)));
        file_put_contents($fn, implode("\n", $config));
        $csr = openssl_csr_new([
            'countryName' => 'NL',
            'commonName'  => $primaryDomain,
        ], $key, [
            'config'         => $fn,
            'req_extensions' => 'SAN',
            'digest_alg'     => 'sha512',
        ]);
        unlink($fn);

        if ($csr === false) {
            throw new \Exception('Could not create a CSR');
        }

        if ($csr === true) {
            throw new \Exception('CSR created but could not be signed');
        }

        if (openssl_csr_export($csr, $result) == false) {
            throw new \Exception('CRS export failed');
        }

        $result = trim($result);

        return $result;
    }

    /**
     * Make a safe base64 string
     *
     * @param string $data
     *
     * @return string
     */
    public static function toSafeString($data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Get the key information
     *
     * @param \OpenSSLAsymmetricKey $key
     *
     * @return array
     * @throws \Exception
     */
    public static function getKeyDetails($key): array
    {
        $accountDetails = openssl_pkey_get_details($key);
        if ($accountDetails === false) {
            throw new \Exception('Could not load account details');
        }

        return $accountDetails;
    }

    /**
     * Split a two certificate bundle into separate multi line string certificates
     * @param string $chain
     * @return array
     * @throws \Exception
     */
    public static function splitCertificate(string $chain): array
    {
        preg_match(
            '/^(?<domain>-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----)\n'
            . '(?<intermediate>-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----)$/s',
            $chain,
            $certificates
        );

        $domain = $certificates['domain'] ?? null;
        $intermediate = $certificates['intermediate'] ?? null;

        if (!$domain || !$intermediate) {
            throw new \Exception('Could not parse certificate string');
        }

        return [$domain, $intermediate];
    }
}
