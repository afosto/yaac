<?php

namespace Afosto\Acme\Data;

abstract class Data implements DataInterface
{

    /**
     * Return data of this class which should be serialized to JSON
     * More information: https://www.php.net/manual/en/jsonserializable.jsonserialize.php
     * @return array
     */
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * Return JSON string of this class
     * @return false|string
     */
    public function toJson()
    {
        return json_encode($this->jsonSerialize());
    }

    /**
     * Returns instance of class from JSON string
     * @param string $json
     * @return mixed
     */
    abstract public static function fromJson($json);
}