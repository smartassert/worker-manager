<?php

namespace App\Exception;

use App\Enum\MachineProvider;

class UnsupportedProviderException extends \Exception implements UnrecoverableExceptionInterface
{
    public function __construct(
        public ?MachineProvider $provider,
    ) {
        parent::__construct(sprintf('Unsupported provider "%s"', $provider?->value));
    }

    public function getProvider(): ?MachineProvider
    {
        return $this->provider;
    }
}
