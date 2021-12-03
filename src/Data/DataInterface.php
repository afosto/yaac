<?php

namespace Afosto\Acme\Data;

interface DataInterface extends \JsonSerializable
{
    /**
     * Return data of this class which should be serialized to JSON
     * More information: https://www.php.net/manual/en/jsonserializable.jsonserialize.php
     * @return array
     */
    public function jsonSerialize();

    /**
     * Return JSON string of this class
     * @return false|string
     */
    public function toJson();

    /**
     * Returns instance of class from JSON string
     * @param string $json
     * @return mixed
     */
    public static function fromJson($json);
}