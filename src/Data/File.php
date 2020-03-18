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

    /**
     * File constructor.
     * @param string $filename
     * @param string $contents
     */
    public function __construct(string $filename, string $contents)
    {
        $this->contents = $contents;
        $this->filename = $filename;
    }

    /**
     * Return the filename for HTTP validation
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * Return the file contents for HTTP validation
     * @return string
     */
    public function getContents(): string
    {
        return $this->contents;
    }
}
