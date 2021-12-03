<?php

namespace Afosto\Acme\Data;

class Record extends Data
{

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $value;

    /**
     * Record constructor.
     * @param string $name
     * @param string $value
     */
    public function __construct(string $name, string $value)
    {
        $this->name = $name;
        $this->value = $value;
    }

    /**
     * Return the DNS TXT record name for validation
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Return the record value for DNS validation
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @param string $json
     * @return Record
     */
    public static function fromJson($json)
    {
        $data = json_decode($json, true);

        return new Record($data['name'], $data['value']);
    }
}
