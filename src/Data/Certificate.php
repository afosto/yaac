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
    protected $certificate;

    /**
     * @var string
     */
    protected $certificateNoChain;

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
     * @param $certificate
     * @throws \Exception
     */
    public function __construct($privateKey, $csr, $certificate)
    {
        $this->privateKey = $privateKey;
        $this->csr = $csr;
        $this->certificate = $certificate;
        list($this->certificateNoChain, $this->intermediateCertificate) = Helper::splitCertificate($certificate);
        $this->expiryDate = Helper::getCertExpiryDate($certificate);
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
     * Return the certificate as a multi line string
     * @return string
     */
    public function getCertificate($asChain = true): string
    {
        return $asChain ? $this->certificate : $this->certificateNoChain;
    }

    /**
     * Return the intermediate certificate as a multi line string
     * @return string
     */
    public function getIntermediateCertificate(): string
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
