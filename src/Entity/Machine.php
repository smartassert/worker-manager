<?php

namespace App\Entity;

use App\Enum\MachineState;
use Doctrine\ORM\Mapping as ORM;

/**
 * @phpstan-type SerializedMachine array{
 *   id: string,
 *   state: non-empty-string,
 *   ip_addresses: string[],
 *   has_end_state: bool
 * }
 */
#[ORM\Entity]
class Machine implements \JsonSerializable
{
    private const NAME = 'worker-%s';

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
     * @param string[] $ipAddresses
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
            'has_end_state' => in_array($this->state, MachineState::END_STATES),
            'has_active_state' => MachineState::UP_ACTIVE === $this->state,
        ];
    }
}
