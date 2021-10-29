<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\ServiceStatusInspector;

use App\Services\ServiceStatusInspector\DoctrineDbalQueryInspector;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Statement;
use Doctrine\ORM\EntityManagerInterface;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class DoctrineDbalQueryInspectorTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @dataProvider invokeDataProvider
     *
     * @param array<string, scalar> $queryParameters
     */
    public function testInvoke(string $query, array $queryParameters): void
    {
        $statement = \Mockery::mock(Statement::class);
        $statement
            ->shouldReceive('execute')
            ->with($queryParameters)
        ;

        $connection = \Mockery::mock(Connection::class);
        $connection
            ->shouldReceive('prepare')
            ->with($query)
            ->andReturn($statement)
        ;

        $entityManager = \Mockery::mock(EntityManagerInterface::class);
        $entityManager
            ->shouldReceive('getConnection')
            ->andReturn($connection)
        ;

        $inspector = new DoctrineDbalQueryInspector($entityManager, $query, $queryParameters);

        ($inspector)();
    }

    /**
     * @return array<mixed>
     */
    public function invokeDataProvider(): array
    {
        return [
            'query, no parameters' => [
                'query' => 'SELECT 1',
                'queryParameters' => [],
            ],
            'query, has parameters' => [
                'query' => 'SELECT name FROM Entity WHERE id = :id',
                'queryParameters' => [
                    'id' => 123,
                ],
            ],
        ];
    }
}
