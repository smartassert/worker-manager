<?php

namespace App\Exception\MachineProvider;

use App\Exception\AbstractMachineException;

class ProviderMachineNotFoundException extends AbstractMachineException
{
    /**
     * @param non-empty-string $id
     * @param non-empty-string $providerName
     */
    public function __construct(
        string $id,
        private string $providerName,
    ) {
        parent::__construct(
            $id,
            sprintf(
                'Machine "%s" not found with provider "%s"',
                $id,
                $providerName
            )
        );
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }
}
