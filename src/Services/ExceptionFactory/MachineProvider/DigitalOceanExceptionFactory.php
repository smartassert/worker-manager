<?php

namespace App\Services\ExceptionFactory\MachineProvider;

use App\Enum\MachineAction;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\DropletLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\Exception;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Exception\MachineProvider\UnknownRemoteMachineException;
use App\Model\DigitalOcean\RemoteMachine;
use DigitalOceanV2\Entity\RateLimit;
use DigitalOceanV2\Exception\ApiLimitExceededException as VendorApiLimitExceededException;
use DigitalOceanV2\Exception\ExceptionInterface as VendorExceptionInterface;
use DigitalOceanV2\Exception\RuntimeException;
use DigitalOceanV2\Exception\ValidationFailedException;

class DigitalOceanExceptionFactory
{
    /**
     * @param non-empty-string $machineId
     */
    public function create(
        string $machineId,
        MachineAction $action,
        VendorExceptionInterface $exception
    ): ExceptionInterface {
        if ($exception instanceof VendorApiLimitExceededException) {
            $exceptionRateLimit = $exception->rateLimit;
            if ($exceptionRateLimit instanceof RateLimit) {
                $rateLimitReset = $exceptionRateLimit->reset;
            } else {
                $rateLimitReset = 0;
            }

            return new ApiLimitExceededException($rateLimitReset, $machineId, $action, $exception);
        }

        if (
            $exception instanceof ValidationFailedException
            && str_contains($exception->getMessage(), DropletLimitExceededException::MESSAGE_IDENTIFIER)
        ) {
            return new DropletLimitExceededException($machineId, $action, $exception);
        }

        if ($exception instanceof RuntimeException) {
            if (401 === $exception->getCode()) {
                return new AuthenticationException($machineId, $action, $exception);
            }

            if (404 === $exception->getCode()) {
                return new UnknownRemoteMachineException(RemoteMachine::TYPE, $machineId, $action, $exception);
            }

            return new HttpException($machineId, $action, $exception);
        }

        return new Exception($machineId, $action, $exception);
    }
}
