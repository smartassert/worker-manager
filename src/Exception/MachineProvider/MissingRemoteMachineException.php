<?php

namespace App\Exception\MachineProvider;

use App\Exception\MachineProvider\AbstractNotFoundRemoteMachineException as Base;
use App\Exception\UnrecoverableExceptionInterface;

class MissingRemoteMachineException extends Base implements UnrecoverableExceptionInterface
{
}
