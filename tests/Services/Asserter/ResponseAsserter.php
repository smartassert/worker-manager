<?php

declare(strict_types=1);

namespace App\Tests\Services\Asserter;

use App\Enum\MachineState;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

class ResponseAsserter
{
    public function assertHealthCheckResponse(ResponseInterface $response): void
    {
        $this->assertJsonResponse(
            $response,
            200,
            [
                'database_connection' => true,
                'database_entities' => true,
                'message_queue' => true,
                'machine_provider_digital_ocean' => true,
            ]
        );
    }

    public function assertStatusResponse(
        ResponseInterface $response,
        string $expectedVersion,
        bool $expectedReady
    ): void {
        $this->assertJsonResponse(
            $response,
            200,
            [
                'version' => $expectedVersion,
                'ready' => $expectedReady,
            ]
        );
    }

    /**
     * @param array<mixed> $expectedResponseData
     */
    public function assertMachineCreateBadRequestResponse(
        ResponseInterface $response,
        array $expectedResponseData
    ): void {
        $this->assertJsonResponse($response, 400, $expectedResponseData);
    }

    /**
     * @param null|string[]     $expectedIpAddresses
     * @param null|array<mixed> $expectedCreateFailureData
     */
    public function assertMachineStatusResponse(
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

        $this->assertMachineResponse(
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
    public function assertMachineDeleteResponse(
        ResponseInterface $response,
        string $expectedMachineId,
        bool $expectedHasEndState,
        ?array $expectedIpAddresses
    ): void {
        $this->assertMachineResponse(
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
    private function assertJsonResponse(
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
