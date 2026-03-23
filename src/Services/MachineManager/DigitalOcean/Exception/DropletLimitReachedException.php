<?php

namespace App\Services\MachineManager\DigitalOcean\Exception;

class DropletLimitReachedException extends ErrorException
{
    public const string MESSAGE_IDENTIFIER = 'exceed your droplet limit';
}
