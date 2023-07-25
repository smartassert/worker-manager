<?php

namespace App\Entity;

use App\Enum\MachineState;
use App\Enum\MachineStateCategory;
use Doctrine\ORM\Mapping as ORM;

/**
 * @phpstan-type SerializedMachine array{
 *   id: non-empty-string,
 *   state: non-empty-string,
 *   ip_addresses: string[],
 *   state_category: non-empty-string
 * }
 */
#[ORM\Entity]
class Machine implements \JsonSerializable
{
    private const NAME = 'worker-%s';

    /**
     * @var non-empty-string
     */
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: MachineIdInterface::LENGTH)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255, enumType: MachineState::class)]
    private MachineState $state;

    /**
     * @var string[]
     */
    #[ORM\Column(type: 'simple_array', nullable: true)]
    private array $ip_addresses;

    /**
     * @param non-empty-string $id
     * @param string[]         $ipAddresses
     */
    public function __construct(
        string $id,
        MachineState $state = MachineState::CREATE_RECEIVED,
        array $ipAddresses = [],
    ) {
        $this->id = $id;
        $this->state = $state;
        $this->ip_addresses = $ipAddresses;
    }

    /**
     * @return non-empty-string
     */
    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return sprintf(self::NAME, $this->id);
    }

    public function getState(): MachineState
    {
        return $this->state;
    }

    public function setState(MachineState $state): void
    {
        $this->state = $state;
    }

    /**
     * @return string[]
     */
    public function getIpAddresses(): array
    {
        return $this->ip_addresses;
    }

    /**
     * @param string[] $ipAddresses
     */
    public function setIpAddresses(array $ipAddresses): void
    {
        $this->ip_addresses = $ipAddresses;
    }

    /**
     * @return SerializedMachine
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'state' => $this->state->value,
            'ip_addresses' => $this->ip_addresses,
            'state_category' => $this->getStateCategory()->value,
        ];
    }

    private function getStateCategory(): MachineStateCategory
    {
        if (in_array($this->state, MachineState::FINDING_STATES)) {
            return MachineStateCategory::FINDING;
        }

        if (in_array($this->state, MachineState::PRE_ACTIVE_STATES)) {
            return MachineStateCategory::PRE_ACTIVE;
        }

        if (MachineState::UP_ACTIVE === $this->state) {
            return MachineStateCategory::ACTIVE;
        }

        if (in_array($this->state, MachineState::ENDING_STATES)) {
            return MachineStateCategory::ENDING;
        }

        if (in_array($this->state, MachineState::END_STATES)) {
            return MachineStateCategory::END;
        }

        return MachineStateCategory::UNKNOWN;
    }
}
