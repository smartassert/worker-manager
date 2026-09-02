<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\ActionFailureType;
use App\Enum\MachineAction;

/**
 * @phpstan-type SerializedActionFailure array{
 *    action: value-of<MachineAction>,
 *    type: value-of<ActionFailureType>,
 *    context: array<string, null|int|string>
 *  }
 */
interface SerializableActionFailureInterface extends \JsonSerializable
{
    /**
     * @return SerializedActionFailure
     */
    public function jsonSerialize(): array;

    /**
     * @return SerializedActionFailure
     */
    public function toArray(): array;
}
