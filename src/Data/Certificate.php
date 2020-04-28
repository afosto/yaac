<?php

namespace Afosto\Acme\Data;

use Afosto\Acme\Helper;

class Certificate
{

    /**
     * @var string
     */
    protected $privateKey;

    /**
     * @var string
     */
    protected $chain;

    /**
     * @var string
     */
    protected $certificate;

    /**
     * @var string
     */
    protected $intermediateCertificate;

    /**
     * @var string
     */
    protected $csr;

    /**
     * @var \DateTime
     */
    protected $expiryDate;

    /**
     * Certificate constructor.
     * @param $privateKey
     * @param $csr
     * @param $chain
     * @throws \Exception
     */
    public function __construct($privateKey, $csr, $chain)
    {
        $this->privateKey = $privateKey;
        $this->csr = $csr;
        $this->chain = $chain;
        list($this->certificate, $this->intermediateCertificate) = Helper::splitCertificate($chain);
        $this->expiryDate = Helper::getCertExpiryDate($chain);
    }

    /**
     * Get the certificate signing request
     * @return string
     */
    public function getCsr(): string
    {
        return $this->csr;
    }

    /**
     * Get the expiry date of the current certificate
     * @return \DateTime
     */
    public function getExpiryDate(): \DateTime
    {
        return $this->expiryDate;
    }

    /**
     * Return the certificate as a multi line string, by default it includes the intermediate certificate as well
     *
     * @param bool $asChain
     * @return string
     */
    public function getCertificate($asChain = true): string
    {
        return $asChain ? $this->chain : $this->certificate;
    }

    /**
     * Return the intermediate certificate as a multi line string
     * @return string
     */
    public function getIntermediate(): string
    {
        return $this->intermediateCertificate;
    }

    /**
     * Return the private key as a multi line string
     * @return string
     */
    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }
}
