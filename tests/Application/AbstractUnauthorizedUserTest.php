<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Tests\Model\Machine;
use App\Tests\Services\AuthenticationConfiguration;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractUnauthorizedUserTest extends AbstractApplicationTest
{
    /**
     * @dataProvider unauthorizedUserDataProvider
     */
    public function testMachineCreateUnauthorizedUser(callable $tokenCreator): void
    {
        $response = $this->applicationClient->makeMachineCreateRequest(
            $tokenCreator(self::$authenticationConfiguration),
            Machine::createId()
        );

        $this->assertUnauthorizedResponse($response);
    }

    /**
     * @dataProvider unauthorizedUserDataProvider
     */
    public function testMachineStatusUnauthorizedUser(callable $tokenCreator): void
    {
        $response = $this->applicationClient->makeMachineStatusRequest(
            $tokenCreator(self::$authenticationConfiguration),
            Machine::createId()
        );

        $this->assertUnauthorizedResponse($response);
    }

    /**
     * @dataProvider unauthorizedUserDataProvider
     */
    public function testMachineDeleteUnauthorizedUser(callable $tokenCreator): void
    {
        $response = $this->applicationClient->makeMachineDeleteRequest(
            $tokenCreator(self::$authenticationConfiguration),
            Machine::createId()
        );

        $this->assertUnauthorizedResponse($response);
    }

    /**
     * @return array<mixed>
     */
    public function unauthorizedUserDataProvider(): array
    {
        return [
            'no token' => [
                'tokenCreator' => function () {
                    return null;
                }
            ],
            'empty token' => [
                'tokenCreator' => function () {
                    return '';
                }
            ],
            'non-empty invalid token' => [
                'tokenCreator' => function (AuthenticationConfiguration $authenticationConfiguration) {
                    return $authenticationConfiguration->getInvalidApiToken();
                }
            ],
        ];
    }

    private function assertUnauthorizedResponse(ResponseInterface $response): void
    {
        Assert::assertSame(401, $response->getStatusCode());
        Assert::assertSame('', $response->getBody()->getContents());
    }
}
