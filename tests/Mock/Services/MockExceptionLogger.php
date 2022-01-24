<?php

declare(strict_types=1);

namespace App\Tests\Mock\Services;

use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use SmartAssert\InvokableLogger\ExceptionLogger;

class MockExceptionLogger
{
    private ExceptionLogger $mock;

    public function __construct()
    {
        $this->mock = \Mockery::mock(ExceptionLogger::class);
    }

    public function getMock(): ExceptionLogger
    {
        return $this->mock;
    }

    /**
     * @param \Throwable[] $exceptions
     */
    public function withLogCalls(array $exceptions): self
    {
        if (!$this->mock instanceof MockInterface) {
            return $this;
        }

        $iteration = 0;

        $this->mock
            ->shouldReceive('log')
            ->times(count($exceptions))
            ->withArgs(function (\Throwable $exception) use ($exceptions, &$iteration) {
                $expectedException = $exceptions[$iteration];
                ++$iteration;

                TestCase::assertSame($expectedException::class, $exception::class);
                TestCase::assertSame($expectedException->getMessage(), $exception->getMessage());
                TestCase::assertSame($expectedException->getCode(), $exception->getCode());

                return true;
            })
        ;

        return $this;
    }

    public function withoutLogCall(): self
    {
        if ($this->mock instanceof MockInterface) {
            $this->mock
                ->shouldNotReceive('log')
            ;
        }

        return $this;
    }
}
