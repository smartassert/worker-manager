<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Tests\Application\AbstractStatusTestCase;

class StatusTest extends AbstractStatusTestCase
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
