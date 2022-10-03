<?php

namespace App\Entity;

use App\Model\ProviderInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class MachineProvider
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=MachineIdInterface::LENGTH)
     */
    private string $id;

    /**
     * @ORM\Column(type="string", length=255)
     *
     * @var ProviderInterface::NAME_*
     */
    private string $provider;

    /**
     * @param ProviderInterface::NAME_* $provider
     */
    public function __construct(string $id, string $provider)
    {
        $this->id = $id;
        $this->provider = $provider;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return ProviderInterface::NAME_*
     */
    public function getName(): string
    {
        return $this->provider;
    }

    /**
     * @param ProviderInterface::NAME_* $name
     */
    public function setName(string $name): void
    {
        $this->provider = $name;
    }

    public function merge(MachineProvider $machineProvider): self
    {
        $this->provider = $machineProvider->getName();

        return $this;
    }
}
