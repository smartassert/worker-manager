<?php

namespace App\Entity;

use App\Enum\ActionFailure\Code;
use App\Enum\ActionFailure\Reason;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ActionFailure implements \JsonSerializable
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: MachineIdInterface::LENGTH)]
    private string $id;

    #[ORM\Column(type: 'integer', enumType: Code::class)]
    private Code $code;

    #[ORM\Column(type: 'text', enumType: Reason::class)]
    private Reason $reason;

    /**
     * @var array<string, int|string>
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $context = [];

    /**
     * @param array<string, int|string> $context
     */
    public function __construct(string $machineId, Code $code, Reason $reason, array $context = [])
    {
        $this->id = $machineId;
        $this->code = $code;
        $this->reason = $reason;
        $this->context = $context;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return array{code: value-of<Code>, reason: value-of<Reason>, context: array<string, int|string>}
     */
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code->value,
            'reason' => $this->reason->value,
            'context' => $this->context,
        ];
    }
}
