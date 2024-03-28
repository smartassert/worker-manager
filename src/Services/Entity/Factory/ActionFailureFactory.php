<?php

namespace App\Services\Entity\Factory;

use App\Entity\ActionFailure;
use App\Enum\ActionFailure\Code;
use App\Enum\ActionFailure\Reason;
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

        $code = $this->findCode($throwable);

        $entity = new ActionFailure(
            $machineId,
            $code,
            $this->findReason($code),
            $action,
            $this->createContext($throwable)
        );
        $this->repository->add($entity);

        return $entity;
    }

    private function findCode(\Throwable $throwable): Code
    {
        if ($throwable instanceof UnsupportedProviderException) {
            return Code::UNSUPPORTED_PROVIDER;
        }

        if ($throwable instanceof ApiLimitExceptionInterface) {
            return Code::API_LIMIT_EXCEEDED;
        }

        if ($throwable instanceof AuthenticationExceptionInterface) {
            return Code::API_AUTHENTICATION_FAILURE;
        }

        if ($throwable instanceof CurlExceptionInterface) {
            return Code::CURL_ERROR;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            return Code::HTTP_ERROR;
        }

        if ($throwable instanceof UnprocessableRequestExceptionInterface) {
            return Code::UNPROCESSABLE_REQUEST;
        }

        if ($throwable instanceof UnknownExceptionInterface) {
            return Code::UNKNOWN_MACHINE_PROVIDER_ERROR;
        }

        return Code::UNKNOWN;
    }

    private function findReason(Code $code): Reason
    {
        $reasons = [
            Code::UNSUPPORTED_PROVIDER->value => Reason::UNSUPPORTED_PROVIDER,
            Code::API_LIMIT_EXCEEDED->value => Reason::API_LIMIT_EXCEEDED,
            Code::API_AUTHENTICATION_FAILURE->value => Reason::API_AUTHENTICATION_FAILURE,
            Code::CURL_ERROR->value => Reason::CURL_ERROR,
            Code::HTTP_ERROR->value => Reason::HTTP_ERROR,
            Code::UNPROCESSABLE_REQUEST->value => Reason::UNPROCESSABLE_REQUEST,
            Code::UNKNOWN_MACHINE_PROVIDER_ERROR->value => Reason::UNKNOWN_MACHINE_PROVIDER_ERROR,
        ];

        return $reasons[$code->value] ?? Reason::UNKNOWN;
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
