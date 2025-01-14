<?php

namespace App\Services\MachineManager\DigitalOcean\Request;

readonly class GetDropletRequest implements RequestInterface
{
    private const int DROPLETS_PER_PAGE = 1;
    private const int DROPLET_PAGE = 1;

    public function __construct(
        private string $name,
    ) {
    }

    public function getMethod(): string
    {
        return 'GET';
    }

    public function getUrl(): string
    {
        return sprintf(
            '/droplets?tag_name=%s&page=%d&per_page=%d',
            $this->name,
            self::DROPLET_PAGE,
            self::DROPLETS_PER_PAGE,
        );
    }
}
