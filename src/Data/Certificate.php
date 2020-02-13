<?php

namespace Afosto\LetsEncrypt\Data;

use Afosto\LetsEncrypt\Helper;

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
        $this->expiryDate = Helper::getCertExpiryDate($certificate);
    }

    /**
     * @return string
     */
    public function getCsr(): string
    {
        return $this->csr;
    }

    /**
     * @return \DateTime
     */
    public function getExpiryDate(): \DateTime
    {
        return $this->expiryDate;
    }

    /**
     * @return string
     */
    public function getCertificate(): string
    {
        return $this->certificate;
    }

    /**
     * @return string
     */
    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }
}
