<?php

namespace App\Entity;

use App\Model\Machine\State;
use App\Model\ProviderInterface;
use App\Repository\MachineRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=MachineRepository::class)
 */
class Machine implements \Stringable, \JsonSerializable
{
    private const NAME = 'worker-%s';

    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=64)
     */
    private string $id;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $remote_id;

    /**
     * @ORM\Column(type="string", length=255)
     *
     * @var \App\Model\Machine\State::VALUE_*
     */
    private string $state;

    /**
     * @ORM\Column(type="string", length=255)
     *
     * @var ProviderInterface::NAME_*
     */
    private string $provider;

    /**
     * @ORM\Column(type="simple_array", nullable=true)
     *
     * @var string[]
     */
    private array $ip_addresses = [];

    /**
     * @param ProviderInterface::NAME_* $provider
     */
    public static function create(string $id, string $provider): self
    {
        $machine = new Machine();
        $machine->id = $id;
        $machine->remote_id = null;
        $machine->state = STATE::VALUE_CREATE_RECEIVED;
        $machine->provider = $provider;
        $machine->ip_addresses = [];

        return $machine;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getRemoteId(): ?int
    {
        return $this->remote_id;
    }

    public function setRemoteId(int $remoteId): self
    {
        $this->remote_id = $remoteId;

        return $this;
    }

    /**
     * @return ProviderInterface::NAME_*
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getName(): string
    {
        return sprintf(self::NAME, (string) $this);
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
    public function setIpAddresses(array $ipAddresses): self
    {
        $ipAddresses = array_filter($ipAddresses, function ($value) {
            return is_string($value) && '' !== trim($value);
        });

        $ipAddresses = array_unique($ipAddresses);

        sort($ipAddresses);

        $this->ip_addresses = $ipAddresses;

        return $this;
    }

    /**
     * @return \App\Model\Machine\State::VALUE_*
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @param  \App\Model\Machine\State::VALUE_* $state
     */
    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }

    /**
     * @return array<mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'state' => $this->state,
            'ip_addresses' => $this->ip_addresses,
        ];
    }
}