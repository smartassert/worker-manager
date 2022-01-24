<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception\MachineProvider\DigitalOcean;

use App\Exception\MachineProvider\DigitalOcean\ApiLimitExceededException;
use App\Exception\UnrecoverableExceptionInterface;
use App\Model\MachineActionInterface;
use PHPUnit\Framework\TestCase;

class ApiLimitExceededExceptionTest extends TestCase
{
    public function testIsUnrecoverable(): void
    {
        $exception = new ApiLimitExceededException(0, '', MachineActionInterface::ACTION_GET, new \Exception());

        self::assertInstanceOf(UnrecoverableExceptionInterface::class, $exception);
    }
}
