<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\MachineProvider;
use App\Model\DigitalOcean\RemoteMachine;
use App\Tests\Functional\AbstractEntityTestCase;
use App\Tests\Services\EntityRemover;

class MachineProviderTest extends AbstractEntityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(MachineProvider::class);
        }
    }

    public function testEntityMapping(): void
    {
        $repository = $this->entityManager->getRepository(MachineProvider::class);
        self::assertCount(0, $repository->findAll());

        $entity = new MachineProvider(self::MACHINE_ID, RemoteMachine::TYPE);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        self::assertCount(1, $repository->findAll());
    }
}
