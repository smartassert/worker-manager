<?php

declare(strict_types=1);

namespace App\Tests\Integration\InvalidMachineProviderCredentials;

use App\Entity\ActionFailure;
use App\Enum\ActionFailure\Code;
use App\Enum\ActionFailure\Reason;
use App\Enum\MachineAction;
use App\Enum\MachineState;
use App\Repository\ActionFailureRepository;
use App\Tests\Application\AbstractMachineTest;
use App\Tests\Integration\GetApplicationClientTrait;
use App\Tests\Model\Machine;

class MachineCreationTest extends AbstractMachineTest
{
    use GetApplicationClientTrait;

    private const MAX_DURATION_IN_SECONDS = 120;
    private const MICROSECONDS_PER_SECOND = 1000000;

    private string $machineId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->machineId = md5((string) rand());
    }

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
                Code::API_AUTHENTICATION_FAILURE,
                Reason::API_AUTHENTICATION_FAILURE,
                MachineAction::FIND,
                []
            ),
            $actionFailure
        );
    }

    private function waitUntilMachineStateIs(MachineState $stopState): bool
    {
        $duration = 0;
        $maxDuration = self::MAX_DURATION_IN_SECONDS * self::MICROSECONDS_PER_SECOND;
        $intervalInMicroseconds = 100000;

        while ($stopState !== $this->getMachine()->getState()) {
            usleep($intervalInMicroseconds);
            $duration += $intervalInMicroseconds;

            if ($duration >= $maxDuration) {
                return false;
            }
        }

        return true;
    }

    private function getMachine(): Machine
    {
        $response = $this->makeValidStatusRequest($this->machineId);
        self::assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);
        self::assertIsArray($data);

        return new Machine($data);
    }

    private function assertEventualMachineState(MachineState $state): void
    {
        $waitResult = $this->waitUntilMachineStateIs($state);
        if (false === $waitResult) {
            $this->fail(sprintf(
                'Timed out waiting for expected machine state. Expected: %s, actual: %s',
                $state->value,
                $this->getMachine()->getState()->value
            ));
        }

        self::assertSame($state, $this->getMachine()->getState());
    }
}
