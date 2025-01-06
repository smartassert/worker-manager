<?php

declare(strict_types=1);

namespace App\Message;

abstract class AbstractRemoteMachineRequest extends AbstractMachineRequest implements ChainedMachineRequestInterface
{
    /**
     * @var MachineRequestInterface[]
     */
    protected array $onSuccessCollection;

    /**
     * @var MachineRequestInterface[]
     */
    protected array $onFailureCollection;

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
