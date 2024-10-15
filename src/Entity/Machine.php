<?php

namespace App\Entity;

use App\Enum\MachineProvider;
use App\Enum\MachineState;
use App\Enum\MachineStateCategory;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Machine
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
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $ip_addresses;

    #[ORM\Column(type: 'string', length: 255, enumType: MachineProvider::class, nullable: true)]
    private ?MachineProvider $provider;

    /**
     * @param non-empty-string $id
     */
    public function __construct(string $id)
    {
        $this->id = $id;
        $this->state = MachineState::UNKNOWN;
        $this->ip_addresses = [];
        $this->provider = null;
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

    public function setProvider(MachineProvider $provider): void
    {
        $this->provider = $provider;
    }

    public function getProvider(): ?MachineProvider
    {
        return $this->provider;
    }

    public function getStateCategory(): MachineStateCategory
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
