<?php

namespace App\Exception\MachineProvider;

use App\Exception\MachineProvider\AbstractNotFoundRemoteMachineException as Base;
use App\Exception\MachineProvider\NotFoundRemoteMachineExceptionInterface as NotFoundRemoteMachineException;

class UnknownRemoteMachineException extends Base implements NotFoundRemoteMachineException
{
}
