<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\Entity\Store;

use App\Entity\Machine;
use App\Services\Entity\Store\MachineStore;
use App\Tests\Functional\AbstractEntityTest;
use App\Tests\Services\EntityRemover;

class MachineStoreTest extends AbstractEntityTest
{
    private MachineStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $store = self::getContainer()->get(MachineStore::class);
        \assert($store instanceof MachineStore);
        $this->store = $store;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(Machine::class);
        }
    }

    public function testStore(): void
    {
        $entity = new Machine(self::MACHINE_ID);

        $repository = $this->entityManager->getRepository($entity::class);
        self::assertCount(0, $repository->findAll());

        $this->store->store($entity);

        self::assertCount(1, $repository->findAll());
    }

    public function testStoreOverwritesExistingEntity(): void
    {
        $repository = $this->entityManager->getRepository(Machine::class);
        self::assertCount(0, $repository->findAll());

        $existingEntity = new Machine(self::MACHINE_ID);
        $this->store->store($existingEntity);
        self::assertCount(1, $repository->findAll());

        $newEntity = new Machine(self::MACHINE_ID, Machine::STATE_UP_ACTIVE, ['127.0.0.1']);
        $this->store->store($newEntity);
        self::assertCount(1, $repository->findAll());
    }
}
