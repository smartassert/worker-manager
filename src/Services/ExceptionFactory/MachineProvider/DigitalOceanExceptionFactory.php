<?php

namespace App\Services\ExceptionFactory\MachineProvider;

use App\Enum\MachineAction;
use App\Enum\MachineProvider;
use App\Exception\MachineProvider\AuthenticationException;
use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\DropletLimitExceededException;
use App\Exception\MachineProvider\DigitalOcean\HttpException;
use App\Exception\MachineProvider\Exception;
use App\Exception\MachineProvider\ExceptionInterface;
use App\Exception\MachineProvider\MissingRemoteMachineException;
use App\Exception\MachineProvider\UnknownRemoteMachineException;
use App\Exception\NoDigitalOceanClientException;
use DigitalOceanV2\Entity\RateLimit;
use DigitalOceanV2\Exception\ApiLimitExceededException as VendorApiLimitExceededException;
use DigitalOceanV2\Exception\ExceptionInterface as VendorExceptionInterface;
use DigitalOceanV2\Exception\ResourceNotFoundException;
use DigitalOceanV2\Exception\RuntimeException;
use DigitalOceanV2\Exception\ValidationFailedException;

class DigitalOceanExceptionFactory implements ExceptionFactoryInterface
{
    public function handles(\Throwable $exception): bool
    {
        return $exception instanceof VendorExceptionInterface || $exception instanceof NoDigitalOceanClientException;
    }

    public function create(string $resourceId, MachineAction $action, \Throwable $exception): ExceptionInterface
    {
        if ($exception instanceof VendorApiLimitExceededException) {
            $exceptionRateLimit = $exception->rateLimit;
            if ($exceptionRateLimit instanceof RateLimit) {
                $rateLimitReset = $exceptionRateLimit->reset;
            } else {
                $rateLimitReset = 0;
            }

            return new ApiLimitExceededException($rateLimitReset, $resourceId, $action, $exception);
        }

        if (
            $exception instanceof ValidationFailedException
            && str_contains($exception->getMessage(), DropletLimitExceededException::MESSAGE_IDENTIFIER)
        ) {
            return new DropletLimitExceededException($resourceId, $action, $exception);
        }

        if ($exception instanceof NoDigitalOceanClientException) {
            return new AuthenticationException(
                MachineProvider::DIGITALOCEAN,
                $resourceId,
                $action,
                $exception->getExceptionStack()
            );
        }

        if ($exception instanceof ResourceNotFoundException) {
            if (MachineAction::GET === $action) {
                return new MissingRemoteMachineException(
                    MachineProvider::DIGITALOCEAN,
                    $resourceId,
                    $action,
                    $exception
                );
            }

            return new UnknownRemoteMachineException(
                MachineProvider::DIGITALOCEAN,
                $resourceId,
                $action,
                $exception
            );
        }

        if ($exception instanceof RuntimeException) {
            return new HttpException($resourceId, $action, $exception);
        }

        return new Exception($resourceId, $action, $exception);
    }
}
