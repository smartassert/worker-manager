<?php

declare(strict_types=1);

namespace App\Tests\Services\Asserter;

use App\Enum\MachineState;
use Psr\Http\Message\ResponseInterface;

class MachineResponseAsserter
{
    public function __construct(
        private readonly ResponseAsserter $responseAsserter,
    ) {
    }

    /**
     * @param null|string[]     $expectedIpAddresses
     * @param null|array<mixed> $expectedCreateFailureData
     */
    public function assertStatusResponse(
        ResponseInterface $response,
        string $expectedMachineId,
        MachineState $expectedState,
        bool $expectedHasEndState,
        bool $expectedHasActiveState,
        ?array $expectedIpAddresses,
        ?array $expectedCreateFailureData = null
    ): void {
        $expectedAdditionalData = null === $expectedCreateFailureData
            ? null :
            ['create_failure' => $expectedCreateFailureData];

        $this->assertResponse(
            $response,
            200,
            $expectedMachineId,
            $expectedState,
            $expectedHasEndState,
            $expectedHasActiveState,
            $expectedIpAddresses,
            $expectedAdditionalData
        );
    }

    /**
     * @param null|string[] $expectedIpAddresses
     */
    public function assertDeleteResponse(
        ResponseInterface $response,
        string $expectedMachineId,
        bool $expectedHasEndState,
        ?array $expectedIpAddresses
    ): void {
        $this->assertResponse(
            $response,
            202,
            $expectedMachineId,
            MachineState::DELETE_RECEIVED,
            $expectedHasEndState,
            false,
            $expectedIpAddresses
        );
    }

    /**
     * @param null|string[] $expectedIpAddresses
     */
    public function assertCreateResponse(
        ResponseInterface $response,
        string $expectedMachineId,
        ?array $expectedIpAddresses
    ): void {
        $this->assertResponse(
            $response,
            202,
            $expectedMachineId,
            MachineState::CREATE_RECEIVED,
            false,
            false,
            $expectedIpAddresses
        );
    }

    /**
     * @param null|string[] $expectedIpAddresses
     * @param array<mixed>  $expectedAdditionalData
     */
    public function assertResponse(
        ResponseInterface $response,
        int $expectedStatusCode,
        string $expectedMachineId,
        MachineState $expectedState,
        bool $expectedHasEndState,
        bool $expectedHasActiveState,
        ?array $expectedIpAddresses,
        ?array $expectedAdditionalData = null,
    ): void {
        $expectedResponseData = [
            'id' => $expectedMachineId,
            'state' => $expectedState->value,
            'has_end_state' => $expectedHasEndState,
            'has_active_state' => $expectedHasActiveState,
        ];

        if (is_array($expectedIpAddresses)) {
            $expectedResponseData['ip_addresses'] = $expectedIpAddresses;
            $excludedKeys = [];
        } else {
            $excludedKeys = ['ip_addresses'];
        }

        if (is_array($expectedAdditionalData)) {
            $expectedResponseData = array_merge($expectedResponseData, $expectedAdditionalData);
        }

        $this->responseAsserter->assertJsonResponse(
            $response,
            $expectedStatusCode,
            $expectedResponseData,
            $excludedKeys
        );
    }
}
