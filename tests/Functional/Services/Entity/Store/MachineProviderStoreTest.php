<?php

declare(strict_types=1);

namespace App\Tests\Functional\Services\Entity\Store;

use App\Entity\MachineProvider;
use App\Model\ProviderInterface;
use App\Services\Entity\Store\MachineProviderStore;
use App\Tests\Functional\AbstractEntityTest;
use App\Tests\Services\EntityRemover;
use webignition\ObjectReflector\ObjectReflector;

class MachineProviderStoreTest extends AbstractEntityTest
{
    private MachineProviderStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $store = self::getContainer()->get(MachineProviderStore::class);
        \assert($store instanceof MachineProviderStore);
        $this->store = $store;

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(MachineProvider::class);
        }
    }

    public function testStore(): void
    {
        $entity = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);

        $repository = $this->entityManager->getRepository($entity::class);
        self::assertCount(0, $repository->findAll());

        $this->store->store($entity);

        self::assertCount(1, $repository->findAll());
    }

    public function testStoreOverwritesExistingEntity(): void
    {
        $repository = $this->entityManager->getRepository(MachineProvider::class);
        self::assertCount(0, $repository->findAll());

        $existingEntity = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        $this->store->store($existingEntity);
        self::assertCount(1, $repository->findAll());

        $newEntity = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);
        ObjectReflector::setProperty($newEntity, MachineProvider::class, 'provider', 'updated provider');

        $this->store->store($newEntity);
        self::assertCount(1, $repository->findAll());
    }

    public function testFind(): void
    {
        $entity = new MachineProvider(self::MACHINE_ID, ProviderInterface::NAME_DIGITALOCEAN);

        $repository = $this->entityManager->getRepository($entity::class);
        self::assertCount(0, $repository->findAll());

        $this->store->store($entity);

        self::assertCount(1, $repository->findAll());
        $this->entityManager->clear();

        $retrievedEntity = $this->store->find(self::MACHINE_ID);

        self::assertEquals($entity, $retrievedEntity);
    }
}
