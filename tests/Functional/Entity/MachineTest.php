<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\Machine;
use App\Enum\MachineState;
use App\Tests\Functional\AbstractEntityTestCase;
use App\Tests\Services\EntityRemover;

class MachineTest extends AbstractEntityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
        }
    }

    public function testEntityMapping(): void
    {
        $repository = $this->entityManager->getRepository(Machine::class);
        self::assertCount(0, $repository->findAll());

        $entity = new Machine(self::MACHINE_ID);
        $entity->setState(MachineState::CREATE_RECEIVED);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        self::assertCount(1, $repository->findAll());
    }
}
