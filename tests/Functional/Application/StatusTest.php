<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Tests\Application\AbstractStatusTest;

class StatusTest extends AbstractStatusTest
{
    use GetApplicationClientTrait;

    protected function getExpectedReadyValue(): bool
    {
        return true;
    }

    protected function getExpectedVersion(): string
    {
        return 'dev';
    }
}
