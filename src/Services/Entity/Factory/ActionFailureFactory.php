<?php

namespace App\Services\Entity\Factory;

use App\Entity\ActionFailure;
use App\Enum\ActionFailureType;
use App\Enum\MachineAction;
use App\Exception\MachineProvider\ApiLimitExceptionInterface;
use App\Exception\MachineProvider\AuthenticationExceptionInterface;
use App\Exception\MachineProvider\CurlExceptionInterface;
use App\Exception\MachineProvider\HttpExceptionInterface;
use App\Exception\MachineProvider\UnknownExceptionInterface;
use App\Exception\MachineProvider\UnprocessableRequestExceptionInterface;
use App\Exception\UnsupportedProviderException;
use App\Repository\ActionFailureRepository;

readonly class ActionFailureFactory
{
    public function __construct(
        private ActionFailureRepository $repository,
    ) {
    }

    public function create(string $machineId, MachineAction $action, \Throwable $throwable): ActionFailure
    {
        $existingEntity = $this->repository->find($machineId);
        if ($existingEntity instanceof ActionFailure) {
            return $existingEntity;
        }

        $type = $this->findType($throwable);

        $entity = new ActionFailure($machineId, $type, $action, $this->createContext($throwable));
        $this->repository->add($entity);

        return $entity;
    }

    private function findType(\Throwable $throwable): ActionFailureType
    {
        if ($throwable instanceof UnsupportedProviderException) {
            return ActionFailureType::UNSUPPORTED_PROVIDER;
        }

        if ($throwable instanceof ApiLimitExceptionInterface) {
            return ActionFailureType::API_LIMIT_EXCEEDED;
        }

        if ($throwable instanceof AuthenticationExceptionInterface) {
            return ActionFailureType::API_AUTHENTICATION_FAILURE;
        }

        if ($throwable instanceof CurlExceptionInterface) {
            return ActionFailureType::CURL_ERROR;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            return ActionFailureType::HTTP_ERROR;
        }

        if ($throwable instanceof UnprocessableRequestExceptionInterface) {
            return ActionFailureType::UNPROCESSABLE_REQUEST;
        }

        if ($throwable instanceof UnknownExceptionInterface) {
            return ActionFailureType::UNKNOWN_MACHINE_PROVIDER_ERROR;
        }

        return ActionFailureType::UNKNOWN;
    }

    /**
     * @return array<string, int|string>
     */
    private function createContext(\Throwable $throwable): array
    {
        if ($throwable instanceof ApiLimitExceptionInterface) {
            return [
                'reset-timestamp' => $throwable->getResetTimestamp(),
            ];
        }

        if ($throwable instanceof CurlExceptionInterface) {
            return [
                'curl-code' => $throwable->getCurlCode(),
            ];
        }

        if ($throwable instanceof HttpExceptionInterface) {
            return [
                'status-code' => $throwable->getStatusCode(),
            ];
        }

        if ($throwable instanceof UnprocessableRequestExceptionInterface) {
            return [
                'provider-reason' => $throwable->getReason(),
            ];
        }

        return [];
    }
}
