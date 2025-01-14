<?php

namespace App\Services\MachineManager\DigitalOcean\Request;

interface RequestInterface
{
    /**
     * @return non-empty-string
     */
    public function getMethod(): string;

    /**
     * @return non-empty-string
     */
    public function getUrl(): string;
}
