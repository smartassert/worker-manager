<?php

declare(strict_types=1);

namespace App\Message;

class CheckMachineIsActive extends AbstractMachineRequest implements ChainedMachineRequestInterface
{
    /**
     * @var MachineRequestInterface[]
     */
    private array $onSuccessCollection;

    /**
     * @var MachineRequestInterface[]
     */
    private array $onFailureCollection;

    /**
     * @param MachineRequestInterface[] $onSuccessCollection
     * @param MachineRequestInterface[] $onFailureCollection
     */
    public function __construct(
        string $uniqueId,
        string $machineId,
        array $onSuccessCollection = [],
        array $onFailureCollection = []
    ) {
        parent::__construct($uniqueId, $machineId);

        $this->onSuccessCollection = $onSuccessCollection;
        $this->onFailureCollection = $onFailureCollection;
    }

    /**
     * @return MachineRequestInterface[]
     */
    public function getOnSuccessCollection(): array
    {
        return $this->onSuccessCollection;
    }

    /**
     * @return MachineRequestInterface[]
     */
    public function getOnFailureCollection(): array
    {
        return $this->onFailureCollection;
    }
}
