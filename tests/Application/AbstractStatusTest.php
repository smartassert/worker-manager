<?php

declare(strict_types=1);

namespace App\Tests\Application;

abstract class AbstractStatusTest extends AbstractApplicationTest
{
    public function testGetStatus(): void
    {
        $this->responseAsserter->assertStatusResponse(
            $this->applicationClient->makeGetStatusRequest(),
            $this->getExpectedVersion(),
            $this->getExpectedReadyValue()
        );
    }

    abstract protected function getExpectedReadyValue(): bool;

    abstract protected function getExpectedVersion(): string;
}
