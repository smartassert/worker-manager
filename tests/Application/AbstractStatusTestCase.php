<?php

declare(strict_types=1);

namespace App\Tests\Application;

abstract class AbstractStatusTestCase extends AbstractApplicationTestCase
{
    public function testGetStatus(): void
    {
        $response = $this->applicationClient->makeGetStatusRequest();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'version' => $this->getExpectedVersion(),
                'ready' => $this->getExpectedReadyValue(),
            ]),
            $response->getBody()->getContents()
        );
    }

    abstract protected function getExpectedReadyValue(): bool;

    abstract protected function getExpectedVersion(): string;
}
