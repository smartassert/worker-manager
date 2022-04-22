<?php

declare(strict_types=1);

namespace App\Tests\Application;

use PHPUnit\Framework\Assert;

abstract class AbstractStatusTest extends AbstractApplicationTest
{
    public function testGetStatus(): void
    {
        $response = $this->applicationClient->makeGetStatusRequest();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));

        $responseData = json_decode($response->getBody()->getContents(), true);
        self::assertIsArray($responseData);

        $expectedResponseData = [
            'version' => $this->getExpectedVersion(),
            'ready' => $this->getExpectedReadyValue(),
        ];

        Assert::assertEquals($expectedResponseData, $responseData);
    }

    abstract protected function getExpectedReadyValue(): bool;

    abstract protected function getExpectedVersion(): string;
}
