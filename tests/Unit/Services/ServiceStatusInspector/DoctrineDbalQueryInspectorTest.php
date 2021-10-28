<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\ServiceStatusInspector;

use App\Services\ServiceStatusInspector\DoctrineDbalQueryInspector;
use Doctrine\DBAL\Tools\Console\Command\RunSqlCommand;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;

class DoctrineDbalQueryInspectorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @dataProvider invokeDataProvider
     */
    public function testInvoke(string $query, ?string $connection, InputInterface $expectedCommandInput): void
    {
        $command = \Mockery::mock(RunSqlCommand::class);
        $command
            ->shouldReceive('run')
            ->withArgs(function (InputInterface $foo) use ($expectedCommandInput) {
                self::assertEquals($expectedCommandInput, $foo);

                return true;
            })
        ;

        $inspector = new DoctrineDbalQueryInspector($command, $query, $connection);

        ($inspector)();
    }

    /**
     * @return array<mixed>
     */
    public function invokeDataProvider(): array
    {
        return [
            'no connection specified' => [
                'query' => 'SELECT 1',
                'connection' => null,
                'expectedCommandInput' => new ArrayInput([
                    'sql' => 'SELECT 1',
                ]),
            ],
            'connection specified' => [
                'query' => 'SELECT 2',
                'connection' => 'connection_name',
                'expectedCommandInput' => new ArrayInput([
                    'sql' => 'SELECT 2',
                    '--connection' => 'connection_name',
                ]),
            ],
        ];
    }
}
