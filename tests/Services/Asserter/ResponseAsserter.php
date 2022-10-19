<?php

declare(strict_types=1);

namespace App\Tests\Services\Asserter;

use App\Entity\Machine;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

class ResponseAsserter
{
    public function assertUnauthorizedResponse(ResponseInterface $response): void
    {
        Assert::assertSame(401, $response->getStatusCode());
        $response->getBody()->rewind();
        Assert::assertSame('', $response->getBody()->getContents());
    }

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
     * @param Machine::STATE_*  $expectedState
     * @param string[]          $expectedIpAddresses
     * @param null|array<mixed> $expectedCreateFailureData
     */
    public function assertMachineStatusResponse(
        ResponseInterface $response,
        string $expectedMachineId,
        string $expectedState,
        array $expectedIpAddresses,
        ?array $expectedCreateFailureData = null,
    ): void {
        $expectedResponseData = [
            'id' => $expectedMachineId,
            'state' => $expectedState,
            'ip_addresses' => $expectedIpAddresses,
        ];

        if (is_array($expectedCreateFailureData)) {
            $expectedResponseData['create_failure'] = $expectedCreateFailureData;
        }

        $this->assertJsonResponse($response, 200, $expectedResponseData);
    }

    public function assertMachineDeleteResponse(ResponseInterface $response, string $expectedMachineId): void
    {
        $this->assertMachineResponse($response, $expectedMachineId, 'delete/received', []);
    }

    /**
     * @param string[] $expectedIpAddresses
     */
    public function assertMachineCreateResponse(
        ResponseInterface $response,
        string $expectedMachineId,
        array $expectedIpAddresses
    ): void {
        $this->assertMachineResponse($response, $expectedMachineId, 'create/received', $expectedIpAddresses);
    }

    /**
     * @param string[] $expectedIpAddresses
     */
    public function assertMachineResponse(
        ResponseInterface $response,
        string $expectedMachineId,
        string $expectedState,
        array $expectedIpAddresses
    ): void {
        $this->assertJsonResponse(
            $response,
            202,
            [
                'id' => $expectedMachineId,
                'state' => $expectedState,
                'ip_addresses' => $expectedIpAddresses,
            ]
        );
    }

    /**
     * @param array<mixed> $expectedResponseData
     */
    private function assertJsonResponse(
        ResponseInterface $response,
        int $expectedStatusCode,
        array $expectedResponseData
    ): void {
        Assert::assertSame($expectedStatusCode, $response->getStatusCode());
        Assert::assertSame('application/json', $response->getHeaderLine('content-type'));

        $responseData = json_decode($response->getBody()->getContents(), true);
        Assert::assertIsArray($responseData);
        Assert::assertEquals($expectedResponseData, $responseData);
    }
}
