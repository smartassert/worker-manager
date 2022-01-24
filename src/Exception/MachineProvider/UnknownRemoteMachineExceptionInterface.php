<?php

namespace App\Exception\MachineProvider;

use App\Exception\UnrecoverableExceptionInterface;
use App\Model\ProviderInterface;

interface UnknownRemoteMachineExceptionInterface extends ExceptionInterface, UnrecoverableExceptionInterface
{
    /**
     * @return ProviderInterface::NAME_*
     */
    public function getProvider(): string;
}
