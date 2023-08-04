<?php

namespace App\Exception\MachineProvider;

use App\Exception\RecoverableDeciderExceptionInterface;

interface UnknownRemoteMachineExceptionInterface extends ExceptionInterface, RecoverableDeciderExceptionInterface
{
    /**
     * @return non-empty-string
     */
    public function getProvider(): string;
}
