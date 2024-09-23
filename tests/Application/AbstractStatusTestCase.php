<?php

declare(strict_types=1);

namespace App\Tests\Application;

abstract class AbstractStatusTestCase extends AbstractApplicationTestCase
{
    public function testGetStatus(): void
    {
        $this->jsonResponseAsserter->assertJsonResponse(
            $this->applicationClient->makeGetStatusRequest(),
            200,
            [
                'version' => $this->getExpectedVersion(),
                'ready' => $this->getExpectedReadyValue(),
            ]
        );
    }

    abstract protected function getExpectedReadyValue(): bool;

    abstract protected function getExpectedVersion(): string;
}
