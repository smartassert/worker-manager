<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception\MachineProvider;

use App\Enum\MachineAction;
use App\Exception\MachineProvider\UnknownRemoteMachineException;
use App\Exception\RecoverableDeciderExceptionInterface;
use App\Model\ProviderInterface;
use PHPUnit\Framework\TestCase;

class UnknownRemoteMachineExceptionTest extends TestCase
{
    public function testIsRecoverableDecider(): void
    {
        self::assertInstanceOf(
            RecoverableDeciderExceptionInterface::class,
            \Mockery::mock(UnknownRemoteMachineException::class)
        );
    }

    /**
     * @dataProvider isRecoverableDataProvider
     */
    public function testIsRecoverable(UnknownRemoteMachineException $exception, bool $expected): void
    {
        self::assertSame($expected, $exception->isRecoverable());
    }

    /**
     * @return array<mixed>
     */
    public function isRecoverableDataProvider(): array
    {
        return [
            'get action not recoverable' => [
                'exception' => new UnknownRemoteMachineException(
                    ProviderInterface::NAME_DIGITALOCEAN,
                    '',
                    MachineAction::GET,
                    new \Exception()
                ),
                'expected' => false,
            ],
            'find action recoverable' => [
                'exception' => new UnknownRemoteMachineException(
                    ProviderInterface::NAME_DIGITALOCEAN,
                    '',
                    MachineAction::FIND,
                    new \Exception()
                ),
                'expected' => true,
            ],
        ];
    }
}
