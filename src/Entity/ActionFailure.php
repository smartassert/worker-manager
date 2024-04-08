<?php

namespace App\Entity;

use App\Enum\ActionFailureType;
use App\Enum\MachineAction;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ActionFailure implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: MachineIdInterface::LENGTH)]
    private string $id;

    #[ORM\Column(type: 'text', enumType: ActionFailureType::class)]
    private ActionFailureType $actionFailureType;

    /**
     * @var array<string, int|string>
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $context;

    #[ORM\Column(type: 'string', enumType: MachineAction::class)]
    private MachineAction $action;

    /**
     * @param array<string, int|string> $context
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

    /**
     * @return array{
     *   action: value-of<MachineAction>,
     *   type: value-of<ActionFailureType>,
     *   context: array<string, int|string>
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'action' => $this->action->value,
            'type' => $this->actionFailureType->value,
            'context' => $this->context,
        ];
    }
}
