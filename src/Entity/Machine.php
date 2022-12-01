<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Machine implements \JsonSerializable
{
    public const STATE_UNKNOWN = 'unknown';
    public const STATE_FIND_RECEIVED = 'find/received';
    public const STATE_FIND_FINDING = 'find/finding';
    public const STATE_FIND_NOT_FOUND = 'find/not-found';
    public const STATE_FIND_NOT_FINDABLE = 'find/not-findable';
    public const STATE_CREATE_RECEIVED = 'create/received';
    public const STATE_CREATE_REQUESTED = 'create/requested';
    public const STATE_CREATE_FAILED = 'create/failed';
    public const STATE_UP_STARTED = 'up/started';
    public const STATE_UP_ACTIVE = 'up/active';
    public const STATE_DELETE_RECEIVED = 'delete/received';
    public const STATE_DELETE_REQUESTED = 'delete/requested';
    public const STATE_DELETE_FAILED = 'delete/failed';
    public const STATE_DELETE_DELETED = 'delete/deleted';

    public const PRE_ACTIVE_STATES = [
        self::STATE_CREATE_RECEIVED,
        self::STATE_CREATE_REQUESTED,
        self::STATE_UP_STARTED,
    ];

    public const END_STATES = [
        self::STATE_CREATE_FAILED,
        self::STATE_DELETE_FAILED,
        self::STATE_DELETE_DELETED,
        self::STATE_FIND_NOT_FINDABLE,
        self::STATE_FIND_NOT_FOUND,
    ];

    public const RESETTABLE_STATES = [
        self::STATE_FIND_NOT_FOUND,
        self::STATE_CREATE_FAILED,
    ];

    private const NAME = 'worker-%s';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: MachineIdInterface::LENGTH)]
    private string $id;

    /**
     * @var self::STATE_*
     */
    #[ORM\Column(type: 'string', length: 255)]
    private string $state;

    /**
     * @var string[]
     */
    #[ORM\Column(type: 'simple_array', nullable: true)]
    private array $ip_addresses;

    /**
     * @param self::STATE_* $state
     * @param string[]      $ipAddresses
     */
    public function __construct(
        string $id,
        string $state = self::STATE_CREATE_RECEIVED,
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

    public function reset(): void
    {
        $this->state = self::STATE_CREATE_RECEIVED;
        $this->ip_addresses = [];
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
