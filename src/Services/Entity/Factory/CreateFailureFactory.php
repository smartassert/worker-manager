<?php

namespace App\Services\Entity\Factory;

use App\Entity\CreateFailure;
use App\Exception\MachineProvider\ApiLimitExceptionInterface;
use App\Exception\MachineProvider\AuthenticationExceptionInterface;
use App\Exception\MachineProvider\CurlExceptionInterface;
use App\Exception\MachineProvider\HttpExceptionInterface;
use App\Exception\MachineProvider\UnknownExceptionInterface;
use App\Exception\MachineProvider\UnprocessableRequestExceptionInterface;
use App\Exception\UnsupportedProviderException;
use App\Services\Entity\Store\CreateFailureStore;

class CreateFailureFactory
{
    /**
     * @var array<CreateFailure::CODE_*, CreateFailure::REASON_*>
     */
    public const REASONS = [
        CreateFailure::CODE_UNSUPPORTED_PROVIDER => CreateFailure::REASON_UNSUPPORTED_PROVIDER,
        CreateFailure::CODE_API_LIMIT_EXCEEDED => CreateFailure::REASON_API_LIMIT_EXCEEDED,
        CreateFailure::CODE_API_AUTHENTICATION_FAILURE => CreateFailure::REASON_API_AUTHENTICATION_FAILURE,
        CreateFailure::CODE_CURL_ERROR => CreateFailure::REASON_CURL_ERROR,
        CreateFailure::CODE_HTTP_ERROR => CreateFailure::REASON_HTTP_ERROR,
        CreateFailure::CODE_UNPROCESSABLE_REQUEST => CreateFailure::REASON_UNPROCESSABLE_REQUEST,
        CreateFailure::CODE_UNKNOWN_MACHINE_PROVIDER_ERROR => CreateFailure::REASON_UNKNOWN_MACHINE_PROVIDER_ERROR,
    ];

    public function __construct(
        private CreateFailureStore $store,
    ) {
    }

    public function create(string $machineId, \Throwable $throwable): CreateFailure
    {
        $existingEntity = $this->store->find($machineId);
        if ($existingEntity instanceof CreateFailure) {
            return $existingEntity;
        }

        $code = $this->findCode($throwable);

        $entity = new CreateFailure($machineId, $code, $this->findReason($code), $this->createContext($throwable));
        $this->store->store($entity);

        return $entity;
    }

    /**
     * @return CreateFailure::CODE_*
     */
    private function findCode(\Throwable $throwable): int
    {
        if ($throwable instanceof UnsupportedProviderException) {
            return CreateFailure::CODE_UNSUPPORTED_PROVIDER;
        }

        if ($throwable instanceof ApiLimitExceptionInterface) {
            return CreateFailure::CODE_API_LIMIT_EXCEEDED;
        }

        if ($throwable instanceof AuthenticationExceptionInterface) {
            return CreateFailure::CODE_API_AUTHENTICATION_FAILURE;
        }

        if ($throwable instanceof CurlExceptionInterface) {
            return CreateFailure::CODE_CURL_ERROR;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            return CreateFailure::CODE_HTTP_ERROR;
        }

        if ($throwable instanceof UnprocessableRequestExceptionInterface) {
            return CreateFailure::CODE_UNPROCESSABLE_REQUEST;
        }

        if ($throwable instanceof UnknownExceptionInterface) {
            return CreateFailure::CODE_UNKNOWN_MACHINE_PROVIDER_ERROR;
        }

        return CreateFailure::CODE_UNKNOWN;
    }

    /**
     * @param CreateFailure::CODE_* $code
     *
     * @return CreateFailure::REASON_*
     */
    private function findReason(int $code): string
    {
        return self::REASONS[$code] ?? CreateFailure::REASON_UNKNOWN;
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
