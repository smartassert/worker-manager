<?php

declare(strict_types=1);

namespace App\Model;

use App\Enum\MachineState;
use App\Enum\MachineStateCategory;

/**
 * @phpstan-import-type SerializedActionFailure from SerializableActionFailureInterface
 *
 * @phpstan-type SerializedMachine array{
 *      id: non-empty-string,
 *      state: value-of<MachineState>,
 *      ip_addresses: string[],
 *      state_category: value-of<MachineStateCategory>,
 *      action_failure: ?SerializedActionFailure,
 *      has_active_state: bool,
 *      has_ending_state: bool,
 *      meta_state: array{
 *        pending: bool,
 *        ended: bool,
 *        succeeded: bool
 *      },
 *      previous_states: value-of<MachineState>[]
 *  }
 */
interface SerializableMachineInterface extends \JsonSerializable
{
    /**
     * @return SerializedMachine
     */
    public function jsonSerialize(): array;

    /**
     * @return SerializedMachine
     */
    public function toArray(): array;
}
