<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class MessageState
{
    public const STATE_CREATED = 'created';
    public const STATE_DISPATCHED = 'dispatched';
    public const STATE_HANDLING = 'handling';
    public const STATE_HANDLED = 'handled';

    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=MachineIdInterface::LENGTH)
     */
    private string $id;

    /**
     * @ORM\Column(type="string", length=255)
     *
     * @var self::STATE_*
     */
    private string $state;

    /**
     * @param self::STATE_* $state
     */
    public function __construct(
        string $id,
        string $state = self::STATE_CREATED
    ) {
        $this->id = $id;
        $this->state = $state;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return self::STATE_*
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @param self::STATE_* $state
     */
    public function setState(string $state): void
    {
        $this->state = $state;
    }
}
