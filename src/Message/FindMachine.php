<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\MachineAction;
use App\Enum\MachineState;

class FindMachine extends AbstractRemoteMachineRequest implements MachineActionInterface
{
    private MachineState $onNotFoundState = MachineState::FIND_NOT_FOUND;
    private bool $reDispatchOnSuccess = false;

    public function withOnNotFoundState(MachineState $onNotFoundState): self
    {
        $new = clone $this;
        $new->onNotFoundState = $onNotFoundState;

        return $new;
    }

    public function getOnNotFoundState(): MachineState
    {
        return $this->onNotFoundState;
    }

    public function withReDispatchOnSuccess(bool $reDispatchOnSuccess): self
    {
        $new = clone $this;
        $new->reDispatchOnSuccess = $reDispatchOnSuccess;

        return $new;
    }

    public function getReDispatchOnSuccess(): bool
    {
        return $this->reDispatchOnSuccess;
    }

    public function getAction(): MachineAction
    {
        return MachineAction::FIND;
    }

    public function getFailureState(): MachineState
    {
        return MachineState::FIND_NOT_FINDABLE;
    }
}
