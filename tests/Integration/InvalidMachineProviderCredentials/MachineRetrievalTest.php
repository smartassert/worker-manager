<?php

declare(strict_types=1);

namespace App\Tests\Integration\InvalidMachineProviderCredentials;

use App\Entity\ActionFailure;
use App\Enum\ActionFailureType;
use App\Enum\MachineAction;
use App\Enum\MachineState;
use App\Repository\ActionFailureRepository;
use App\Tests\Integration\AbstractIntegrationMachineTestCase;

class MachineRetrievalTest extends AbstractIntegrationMachineTestCase
{
    public function testRetrieveRemoteMachineMachineProviderAuthenticationFailure(): void
    {
        $this->makeValidStatusRequest($this->machineId);

        $this->assertEventualMachineState(MachineState::FIND_NOT_FINDABLE);

        $actionFailureRepository = self::getContainer()->get(ActionFailureRepository::class);
        \assert($actionFailureRepository instanceof ActionFailureRepository);

        $actionFailure = $actionFailureRepository->findAll()[0];
        self::assertInstanceOf(ActionFailure::class, $actionFailure);

        self::assertEquals(
            new ActionFailure(
                $this->machineId,
                ActionFailureType::VENDOR_AUTHENTICATION_FAILURE,
                MachineAction::FIND,
                [
                    'provider' => null,
                ]
            ),
            $actionFailure
        );
    }
}
