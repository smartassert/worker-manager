<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\Entity\Store;

use App\Entity\Machine;
use App\Services\Entity\Store\MachineStore;
use App\Tests\Functional\AbstractEntityTest;

class MachineStoreTest extends AbstractEntityTest
{
    private MachineStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $store = self::getContainer()->get(MachineStore::class);
        \assert($store instanceof MachineStore);
        $this->store = $store;
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

    public function testPersistResetsExistingEntity(): void
    {
        $repository = $this->entityManager->getRepository(Machine::class);
        self::assertCount(0, $repository->findAll());

        $entity = new Machine(self::MACHINE_ID, Machine::STATE_CREATE_FAILED, ['10.0.0.1']);
        $this->store->store($entity);
        self::assertCount(1, $repository->findAll());

        $retrievedEntity = $repository->find(self::MACHINE_ID);
        self::assertInstanceOf(Machine::class, $retrievedEntity);

        self::assertSame(Machine::STATE_CREATE_FAILED, $retrievedEntity->getState());
        self::assertSame(['10.0.0.1'], $retrievedEntity->getIpAddresses());

        $entity->reset();
        $this->store->persist($entity);

        self::assertSame(Machine::STATE_CREATE_RECEIVED, $retrievedEntity->getState());
        self::assertSame([], $retrievedEntity->getIpAddresses());
    }

    public function testFind(): void
    {
        $entity = new Machine(self::MACHINE_ID);

        $repository = $this->entityManager->getRepository($entity::class);
        self::assertCount(0, $repository->findAll());

        $this->store->store($entity);

        self::assertCount(1, $repository->findAll());
        $this->entityManager->clear();

        $retrievedEntity = $this->store->find(self::MACHINE_ID);

        self::assertEquals($entity, $retrievedEntity);
    }
}
