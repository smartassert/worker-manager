<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Tests\Model\Machine;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractUnauthorizedUserTestCase extends AbstractApplicationTestCase
{
    /**
     * @dataProvider unauthorizedUserDataProvider
     */
    public function testMachineCreateUnauthorizedUser(?string $token): void
    {
        $response = $this->applicationClient->makeMachineCreateRequest($token, Machine::createId());

        $this->assertUnauthorizedResponse($response);
    }

    /**
     * @dataProvider unauthorizedUserDataProvider
     */
    public function testMachineStatusUnauthorizedUser(?string $token): void
    {
        $response = $this->applicationClient->makeMachineStatusRequest($token, Machine::createId());

        $this->assertUnauthorizedResponse($response);
    }

    /**
     * @dataProvider unauthorizedUserDataProvider
     */
    public function testMachineDeleteUnauthorizedUser(?string $token): void
    {
        $response = $this->applicationClient->makeMachineDeleteRequest($token, Machine::createId());

        $this->assertUnauthorizedResponse($response);
    }

    /**
     * @return array<mixed>
     */
    public static function unauthorizedUserDataProvider(): array
    {
        return [
            'no token' => [
                'token' => null,
            ],
            'empty token' => [
                'token' => '',
            ],
            'non-empty invalid token' => [
                'token' => 'invalid token',
            ],
        ];
    }

    private function assertUnauthorizedResponse(ResponseInterface $response): void
    {
        Assert::assertSame(401, $response->getStatusCode());
        Assert::assertSame('', $response->getBody()->getContents());
    }
}
