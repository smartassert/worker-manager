<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Messenger\Stamp\StampInterface;

class CheckMachineIsActive extends AbstractMachineRequest implements
    ChainedMachineRequestInterface,
    StampedMessageInterface
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
     * @param StampInterface[]          $stamps
     * @param MachineRequestInterface[] $onSuccessCollection
     * @param MachineRequestInterface[] $onFailureCollection
     */
    public function __construct(
        string $uniqueId,
        string $machineId,
        private array $stamps,
        array $onSuccessCollection = [],
        array $onFailureCollection = []
    ) {
        parent::__construct($uniqueId, $machineId);

        $this->onSuccessCollection = array_filter($onSuccessCollection, function ($value) {
            return $value instanceof MachineRequestInterface;
        });

        $this->onFailureCollection = array_filter($onFailureCollection, function ($value) {
            return $value instanceof MachineRequestInterface;
        });
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

    public function getStamps(): array
    {
        return $this->stamps;
    }
}
