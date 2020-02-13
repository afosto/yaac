<?php

namespace Afosto\Acme\Data;

class File
{

    /**
     * @var string
     */
    protected $filename;

    /**
     * @var string
     */
    protected $contents;


    public function __construct(string $filename, string $contents)
    {
        $this->contents = $contents;
        $this->filename = $filename;
    }

    /**
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * @return string
     */
    public function getContents(): string
    {
        return $this->contents;
    }
}
