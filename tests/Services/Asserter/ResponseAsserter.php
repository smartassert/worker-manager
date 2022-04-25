<?php

declare(strict_types=1);

namespace App\Tests\Services\Asserter;

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
        $this->assertJsonResponse($response, [
            'database_connection' => true,
            'database_entities' => true,
            'message_queue' => true,
            'machine_provider_digital_ocean' => true,
        ]);
    }

    public function assertMachineStatusResponse(
        ResponseInterface $response,
        string $expectedVersion,
        bool $expectedReady
    ): void {
        $this->assertJsonResponse($response, [
            'version' => $expectedVersion,
            'ready' => $expectedReady,
        ]);
    }

    /**
     * @param array<mixed> $expectedResponseData
     */
    private function assertJsonResponse(ResponseInterface $response, array $expectedResponseData): void
    {
        Assert::assertSame(200, $response->getStatusCode());
        Assert::assertSame('application/json', $response->getHeaderLine('content-type'));

        $responseData = json_decode($response->getBody()->getContents(), true);
        Assert::assertIsArray($responseData);
        Assert::assertEquals($expectedResponseData, $responseData);
    }
}
