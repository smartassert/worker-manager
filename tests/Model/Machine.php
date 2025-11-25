<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Enum\MachineState;
use Symfony\Component\Uid\Ulid;

class Machine
{
    /**
     * @param array<mixed> $data
     */
    public function __construct(
        private array $data,
    ) {}

    public static function createId(): string
    {
        return (string) new Ulid();
    }

    public function getId(): string
    {
        $id = $this->data['id'] ?? '';

        return is_string($id) ? $id : '';
    }

    public function getState(): MachineState
    {
        $state = $this->data['state'] ?? '';

        foreach (MachineState::cases() as $machineState) {
            if ($machineState->value === $state) {
                return $machineState;
            }
        }

        return MachineState::UNKNOWN;
    }
}
