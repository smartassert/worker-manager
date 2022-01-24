<?php

namespace App\Exception\MachineProvider;

use App\Exception\RecoverableDeciderExceptionInterface;
use App\Model\ProviderInterface;

interface UnknownRemoteMachineExceptionInterface extends ExceptionInterface, RecoverableDeciderExceptionInterface
{
    /**
     * @return ProviderInterface::NAME_*
     */
    public function getProvider(): string;
}
