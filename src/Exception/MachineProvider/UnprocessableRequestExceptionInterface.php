<?php

namespace App\Exception\MachineProvider;

interface UnprocessableRequestExceptionInterface extends ExceptionInterface, HasMachineProviderInterface
{
    public const int CODE_REMOTE_PROVIDER_RESOURCE_LIMIT_REACHED = 100;
    public const int CODE_PROVIDER_IMAGE_INVALID = 100;
    public const string REASON_REMOTE_PROVIDER_RESOURCE_LIMIT_REACHED = 'remote provider resource limit reached';
    public const string REASON_PROVIDER_IMAGE_INVALID = 'remote provider worker image invalid';

    public function getReason(): string;
}
