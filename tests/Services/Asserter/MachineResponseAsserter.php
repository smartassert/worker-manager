<?php

declare(strict_types=1);

namespace App\Tests\Services\Asserter;

use App\Enum\MachineState;
use App\Enum\MachineStateCategory;
use Psr\Http\Message\ResponseInterface;

class MachineResponseAsserter
{
    public function __construct(
        private readonly JsonResponseAsserter $jsonResponseAsserter,
    ) {
    }

    /**
     * @param null|string[] $expectedIpAddresses
     */
    public function assertDeleteResponse(
        ResponseInterface $response,
        string $expectedMachineId,
        ?array $expectedIpAddresses,
    ): void {
        $this->assertResponse(
            $response,
            202,
            $expectedMachineId,
            MachineState::DELETE_RECEIVED,
            MachineStateCategory::ENDING,
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
            MachineStateCategory::PRE_ACTIVE,
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
        MachineStateCategory $expectedStateCategory,
        ?array $expectedIpAddresses,
        ?array $expectedAdditionalData = null,
    ): void {
        $expectedResponseData = [
            'id' => $expectedMachineId,
            'state' => $expectedState->value,
            'state_category' => $expectedStateCategory->value,
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

        $this->jsonResponseAsserter->assertJsonResponse(
            $response,
            $expectedStatusCode,
            $expectedResponseData,
            $excludedKeys
        );
    }
}
