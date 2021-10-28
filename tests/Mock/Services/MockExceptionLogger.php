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

    public function withLogCall(\Throwable $expectedException): self
    {
        if ($this->mock instanceof MockInterface) {
            $this->mock
                ->shouldReceive('log')
                ->withArgs(function (\Throwable $exception) use ($expectedException) {
                    TestCase::assertSame($expectedException::class, $exception::class);
                    TestCase::assertSame($expectedException->getMessage(), $exception->getMessage());
                    TestCase::assertSame($expectedException->getCode(), $exception->getCode());

                    return true;
                })
            ;
        }

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
