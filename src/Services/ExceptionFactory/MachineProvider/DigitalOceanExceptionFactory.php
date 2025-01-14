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
use App\Exception\Stack;
use App\Services\MachineManager\DigitalOcean\Exception\ApiLimitExceededException as DOApiLimitExceededException;
use App\Services\MachineManager\DigitalOcean\Exception\AuthenticationException as DOAuthenticationException;
use App\Services\MachineManager\DigitalOcean\Exception\EmptyDropletCollectionException;
use App\Services\MachineManager\DigitalOcean\Exception\ErrorException;
use App\Services\MachineManager\DigitalOcean\Exception\MissingDropletException;
use DigitalOceanV2\Exception\ExceptionInterface as VendorExceptionInterface;
use DigitalOceanV2\Exception\ValidationFailedException;

class DigitalOceanExceptionFactory implements ExceptionFactoryInterface
{
    public function handles(\Throwable $exception): bool
    {
        return $exception instanceof VendorExceptionInterface
            || $exception instanceof DOApiLimitExceededException
            || $exception instanceof DOAuthenticationException
            || $exception instanceof ErrorException
            || $exception instanceof MissingDropletException;
    }

    public function create(string $resourceId, MachineAction $action, \Throwable $exception): ExceptionInterface
    {
        if ($exception instanceof DOApiLimitExceededException) {
            return new ApiLimitExceededException($exception->rateLimitReset, $resourceId, $action, $exception);
        }

        if (
            $exception instanceof ValidationFailedException
            && str_contains($exception->getMessage(), DropletLimitExceededException::MESSAGE_IDENTIFIER)
        ) {
            return new DropletLimitExceededException($resourceId, $action, $exception);
        }

        if ($exception instanceof DOAuthenticationException) {
            return new AuthenticationException(
                MachineProvider::DIGITALOCEAN,
                $resourceId,
                $action,
                new Stack([$exception])
            );
        }

        if (
            $exception instanceof EmptyDropletCollectionException
            || $exception instanceof MissingDropletException
        ) {
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

        if ($exception instanceof ErrorException) {
            return new HttpException($resourceId, $action, $exception);
        }

        return new Exception($resourceId, $action, $exception);
    }
}
