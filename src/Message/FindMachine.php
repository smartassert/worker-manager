<?php

declare(strict_types=1);

namespace App\Message;

use App\Enum\MachineState;
use App\Model\MachineActionInterface;

class FindMachine extends AbstractRemoteMachineRequest
{
    private MachineState $onNotFoundState = MachineState::FIND_NOT_FOUND;
    private bool $reDispatchOnSuccess = false;

    public function getAction(): string
    {
        return MachineActionInterface::ACTION_FIND;
    }

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
}
