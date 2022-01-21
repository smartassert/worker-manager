<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Entity\Machine as MachineEntity;

class Machine
{
    /**
     * @param array<mixed> $data
     */
    public function __construct(
        private array $data,
    ) {
    }

    public function getId(): string
    {
        $id = $this->data['id'] ?? '';

        return is_string($id) ? $id : '';
    }

    /**
     * @return MachineEntity::STATE_*
     */
    public function getState(): string
    {
        $state = $this->data['state'] ?? '';

        return MachineEntity::STATE_FIND_RECEIVED === $state
            || MachineEntity::STATE_FIND_FINDING === $state
            || MachineEntity::STATE_FIND_NOT_FOUND === $state
            || MachineEntity::STATE_FIND_NOT_FINDABLE === $state
            || MachineEntity::STATE_CREATE_RECEIVED === $state
            || MachineEntity::STATE_CREATE_REQUESTED === $state
            || MachineEntity::STATE_CREATE_FAILED === $state
            || MachineEntity::STATE_UP_STARTED === $state
            || MachineEntity::STATE_UP_ACTIVE === $state
            || MachineEntity::STATE_DELETE_RECEIVED === $state
            || MachineEntity::STATE_DELETE_REQUESTED === $state
            || MachineEntity::STATE_DELETE_FAILED === $state
            || MachineEntity::STATE_DELETE_DELETED === $state
            ? $state
            : MachineEntity::STATE_UNKNOWN;
    }
}
