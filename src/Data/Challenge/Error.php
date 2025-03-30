<?php

namespace Afosto\Acme\Data\Challenge;

class Error
{
    /** @var string|null */
    protected $type;

    /** @var string|null */
    protected $detail;

    /** @var string|null */
    protected $status;

    public function __construct($data = [])
    {
        $this->type = isset($data['type'])? $data['type'] : null;
        $this->detail = isset($data['detail'])? $data['detail'] : null;
        $this->status = isset($data['status'])? $data['status'] : null;
    }

    /**
     * @return string|null
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string|null
     */
    public function getDetail()
    {
        return $this->detail;
    }

    /**
     * @return string|null
     */
    public function getStatus()
    {
        return $this->status;
    }
}
