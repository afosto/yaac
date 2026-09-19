<?php

namespace Afosto\Acme\Data;

class File
{
    public function __construct(
        protected string $filename,
        protected string $contents,
    ) {
    }

    /**
     * Return the filename for HTTP validation
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * Return the file contents for HTTP validation
     */
    public function getContents(): string
    {
        return $this->contents;
    }
}
