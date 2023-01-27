<?php

declare(strict_types=1);

namespace App\Tests\Services\Asserter;

use App\Enum\MachineState;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

class ResponseAsserter
{
    /**
     * @param null|string[] $expectedIpAddresses
     */
    public function assertMachineCreateResponse(
        ResponseInterface $response,
        string $expectedMachineId,
        ?array $expectedIpAddresses
    ): void {
        $this->assertMachineResponse(
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
    public function assertMachineResponse(
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

        $this->assertJsonResponse($response, $expectedStatusCode, $expectedResponseData, $excludedKeys);
    }

    /**
     * @param array<mixed> $expectedResponseData
     * @param string[]     $excludedKeys
     */
    public function assertJsonResponse(
        ResponseInterface $response,
        int $expectedStatusCode,
        array $expectedResponseData,
        array $excludedKeys = [],
    ): void {
        Assert::assertSame($expectedStatusCode, $response->getStatusCode());
        Assert::assertSame('application/json', $response->getHeaderLine('content-type'));

        $responseData = json_decode($response->getBody()->getContents(), true);
        Assert::assertIsArray($responseData);

        foreach ($excludedKeys as $key) {
            unset($responseData[$key], $expectedResponseData[$key]);
        }

        Assert::assertEquals($expectedResponseData, $responseData);
    }
}
