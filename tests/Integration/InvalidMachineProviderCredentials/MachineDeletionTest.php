<?php

declare(strict_types=1);

namespace App\Tests\Integration\InvalidMachineProviderCredentials;

use App\Entity\ActionFailure;
use App\Enum\ActionFailureType;
use App\Enum\MachineAction;
use App\Enum\MachineState;
use App\Enum\MachineStateCategory;
use App\Repository\ActionFailureRepository;
use App\Tests\Integration\AbstractIntegrationMachineTestCase;

class MachineDeletionTest extends AbstractIntegrationMachineTestCase
{
    public function testDeleteRemoteMachineMachineProviderAuthenticationFailure(): void
    {
        $response = $this->makeValidDeleteRequest($this->machineId);

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'id' => $this->machineId,
                'ip_addresses' => [],
                'state' => MachineState::DELETE_RECEIVED,
                'state_category' => MachineStateCategory::ENDING,
                'action_failure' => null,
                'has_failed_state' => false,
                'has_active_state' => false,
                'has_ending_state' => true,
                'has_end_state' => false,
            ]),
            $response->getBody()->getContents()
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
