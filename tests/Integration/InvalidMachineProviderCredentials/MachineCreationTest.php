<?php

declare(strict_types=1);

namespace App\Tests\Integration\InvalidMachineProviderCredentials;

use App\Entity\ActionFailure;
use App\Enum\ActionFailureType;
use App\Enum\MachineAction;
use App\Enum\MachineState;
use App\Repository\ActionFailureRepository;
use App\Tests\Integration\AbstractIntegrationMachineTest;

class MachineCreationTest extends AbstractIntegrationMachineTest
{
    public function testCreateRemoteMachineMachineProviderAuthenticationFailure(): void
    {
        $response = $this->makeValidCreateRequest($this->machineId);
        $this->machineResponseAsserter->assertCreateResponse(
            $response,
            $this->machineId,
            null
        );

        $this->assertEventualMachineState(MachineState::FIND_NOT_FINDABLE);

        $actionFailureRepository = self::getContainer()->get(ActionFailureRepository::class);
        \assert($actionFailureRepository instanceof ActionFailureRepository);

        $actionFailure = $actionFailureRepository->findAll()[0];
        self::assertInstanceOf(ActionFailure::class, $actionFailure);

        self::assertEquals(
            new ActionFailure(
                $this->machineId,
                ActionFailureType::API_AUTHENTICATION_FAILURE,
                MachineAction::FIND,
                []
            ),
            $actionFailure
        );
    }
}
