<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application;

use App\Tests\Application\AbstractUnauthorizedUserTestCase;

class UnauthorizedUserTest extends AbstractUnauthorizedUserTestCase
{
    use GetApplicationClientTrait;
}
