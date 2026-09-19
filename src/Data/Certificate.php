<?php

namespace Afosto\Acme\Data;

use Afosto\Acme\Helper;

class Certificate
{
    protected string $certificate;

    protected string $intermediateCertificate;

    protected \DateTime $expiryDate;

    public function __construct(
        protected string $privateKey,
        protected string $csr,
        protected string $chain,
    ) {
        list($this->certificate, $this->intermediateCertificate) = Helper::splitCertificate($chain);
        $this->expiryDate = Helper::getCertExpiryDate($chain);
    }

    /**
     * Get the certificate signing request
     */
    public function getCsr(): string
    {
        return $this->csr;
    }

    /**
     * Get the expiry date of the current certificate
     */
    public function getExpiryDate(): \DateTime
    {
        return $this->expiryDate;
    }

    /**
     * Return the certificate as a multi line string, by default it includes the intermediate certificate as well
     */
    public function getCertificate(bool $asChain = true): string
    {
        return $asChain ? $this->chain : $this->certificate;
    }

    /**
     * Return the intermediate certificate as a multi line string
     */
    public function getIntermediate(): string
    {
        return $this->intermediateCertificate;
    }

    /**
     * Return the private key as a multi line string
     */
    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }
}
