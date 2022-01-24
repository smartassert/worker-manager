<?php

namespace App\Exception;

class UnsupportedProviderException extends \Exception implements UnrecoverableExceptionInterface
{
    private const MESSAGE = 'Unsupported provider "%s"';

    public function __construct(
        public string $provider,
    ) {
        parent::__construct(sprintf(self::MESSAGE, $provider));
    }

    public function getProvider(): string
    {
        return $this->provider;
    }
}
