<?php

namespace App\Services\MachineManager\DigitalOcean\Request;

readonly class CreateDropletRequest implements RequestInterface
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        private string $name,
        private string $region,
        private string $size,
        private string $image,
        private array $tags,
        private string $userData,
    ) {
    }

    public function getMethod(): string
    {
        return 'POST';
    }

    public function getUrl(): string
    {
        return '/droplets';
    }

    public function getPayload(): ?array
    {
        return [
            'name' => $this->name,
            'region' => $this->region,
            'size' => $this->size,
            'image' => $this->image,
            'tags' => $this->tags,
            'userData' => $this->userData,
        ];
    }
}
