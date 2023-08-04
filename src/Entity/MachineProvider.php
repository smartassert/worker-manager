<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class MachineProvider
{
    /**
     * @var non-empty-string
     */
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: MachineIdInterface::LENGTH)]
    private string $id;

    /**
     * @var non-empty-string
     */
    #[ORM\Column(type: 'string', length: 255)]
    private string $provider;

    /**
     * @param non-empty-string $id
     * @param non-empty-string $provider
     */
    public function __construct(string $id, string $provider)
    {
        $this->id = $id;
        $this->provider = $provider;
    }

    /**
     * @return non-empty-string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return non-empty-string
     */
    public function getName(): string
    {
        return $this->provider;
    }
}
