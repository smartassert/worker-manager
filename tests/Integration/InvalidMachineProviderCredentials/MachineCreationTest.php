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

class MachineCreationTest extends AbstractIntegrationMachineTestCase
{
    public function testCreateRemoteMachineMachineProviderAuthenticationFailure(): void
    {
        $response = $this->makeValidCreateRequest($this->machineId);

        self::assertSame(202, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('content-type'));
        self::assertJsonStringEqualsJsonString(
            (string) json_encode([
                'id' => $this->machineId,
                'ip_addresses' => [],
                'state' => MachineState::CREATE_RECEIVED,
                'state_category' => MachineStateCategory::PRE_ACTIVE,
                'action_failure' => null,
            ]),
            $response->getBody()->getContents()
        );

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
