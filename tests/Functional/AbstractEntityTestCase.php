<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\AbstractBaseFunctionalTestCase;
use Doctrine\ORM\EntityManagerInterface;

abstract class AbstractEntityTestCase extends AbstractBaseFunctionalTestCase
{
    protected const MACHINE_ID = 'machine id';

    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;
    }
}
