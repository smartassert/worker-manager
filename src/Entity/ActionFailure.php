<?php

namespace App\Entity;

use App\Enum\ActionFailureType;
use App\Enum\MachineAction;
use App\Model\SerializableActionFailureInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ActionFailure implements SerializableActionFailureInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: MachineIdInterface::LENGTH)]
    private string $id;

    #[ORM\Column(type: 'text', enumType: ActionFailureType::class)]
    private ActionFailureType $actionFailureType;

    /**
     * @var array<string, null|int|string>
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $context;

    #[ORM\Column(type: 'string', enumType: MachineAction::class)]
    private MachineAction $action;

    /**
     * @param array<string, null|int|string> $context
     */
    public function __construct(
        string $machineId,
        ActionFailureType $actionFailureType,
        MachineAction $action,
        array $context = []
    ) {
        $this->id = $machineId;
        $this->actionFailureType = $actionFailureType;
        $this->context = $context;
        $this->action = $action;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function jsonSerialize(): array
    {
        return [
            'action' => $this->action->value,
            'type' => $this->actionFailureType->value,
            'context' => $this->context,
        ];
    }

    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
