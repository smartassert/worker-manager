<?php

declare(strict_types=1);

namespace App\Tests\Integration\InvalidMachineProviderCredentials;

use App\Entity\ActionFailure;
use App\Enum\ActionFailureType;
use App\Enum\MachineAction;
use App\Enum\MachineState;
use App\Repository\ActionFailureRepository;
use App\Tests\Integration\AbstractIntegrationMachineTest;

class MachineDeletionTest extends AbstractIntegrationMachineTest
{
    public function testDeleteRemoteMachineMachineProviderAuthenticationFailure(): void
    {
        $response = $this->makeValidDeleteRequest($this->machineId);
        $this->machineResponseAsserter->assertDeleteResponse(
            $response,
            $this->machineId,
            null
        );

        $this->assertEventualMachineState(MachineState::DELETE_FAILED);

        $actionFailureRepository = self::getContainer()->get(ActionFailureRepository::class);
        \assert($actionFailureRepository instanceof ActionFailureRepository);

        $actionFailure = $actionFailureRepository->findAll()[0];
        self::assertInstanceOf(ActionFailure::class, $actionFailure);

        self::assertEquals(
            new ActionFailure(
                $this->machineId,
                ActionFailureType::VENDOR_AUTHENTICATION_FAILURE,
                MachineAction::DELETE,
                [
                    'provider' => null,
                ]
            ),
            $actionFailure
        );
    }
}
