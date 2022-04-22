<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Tests\Model\Machine;
use App\Tests\Services\AuthenticationConfiguration;

abstract class AbstractUnauthorizedUserTest extends AbstractApplicationTest
{
    /**
     * @dataProvider unauthorizedUserDataProvider
     */
    public function testMachineCreateUnauthorizedUser(callable $tokenCreator): void
    {
        $response = $this->applicationClient->makeMachineCreateRequest(
            $tokenCreator($this->authenticationConfiguration),
            Machine::createId()
        );

        $this->responseAsserter->assertUnauthorizedResponse($response);
    }

    /**
     * @dataProvider unauthorizedUserDataProvider
     */
    public function testMachineStatusUnauthorizedUser(callable $tokenCreator): void
    {
        $response = $this->applicationClient->makeMachineStatusRequest(
            $tokenCreator($this->authenticationConfiguration),
            Machine::createId()
        );

        $this->responseAsserter->assertUnauthorizedResponse($response);
    }

    /**
     * @dataProvider unauthorizedUserDataProvider
     */
    public function testMachineDeleteUnauthorizedUser(callable $tokenCreator): void
    {
        $response = $this->applicationClient->makeMachineDeleteRequest(
            $tokenCreator($this->authenticationConfiguration),
            Machine::createId()
        );

        $this->responseAsserter->assertUnauthorizedResponse($response);
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
                    return $authenticationConfiguration->invalidToken;
                }
            ],
        ];
    }
}
