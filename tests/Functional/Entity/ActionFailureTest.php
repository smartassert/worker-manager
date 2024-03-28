<?php

declare(strict_types=1);

namespace App\Tests\Functional\Entity;

use App\Entity\ActionFailure;
use App\Enum\ActionFailure\Code;
use App\Enum\ActionFailure\Reason;
use App\Repository\ActionFailureRepository;
use App\Tests\Functional\AbstractEntityTestCase;
use App\Tests\Services\EntityRemover;

class ActionFailureTest extends AbstractEntityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $entityRemover = self::getContainer()->get(EntityRemover::class);
        if ($entityRemover instanceof EntityRemover) {
            $entityRemover->removeAllForEntity(ActionFailure::class);
        }
    }

    public function testEntityMapping(): void
    {
        $repository = $this->entityManager->getRepository(ActionFailure::class);
        self::assertCount(0, $repository->findAll());

        $entity = new ActionFailure(self::MACHINE_ID, Code::UNKNOWN, Reason::UNKNOWN);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        self::assertCount(1, $repository->findAll());
    }

    /**
     * @dataProvider retrieveDataProvider
     */
    public function testRetrieve(ActionFailure $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $this->entityManager->clear();

        $actionFailureRepository = self::getContainer()->get(ActionFailureRepository::class);
        \assert($actionFailureRepository instanceof ActionFailureRepository);

        $retrievedEntity = $actionFailureRepository->find(self::MACHINE_ID);
        self::assertInstanceOf(ActionFailure::class, $retrievedEntity);
        self::assertSame($entity->jsonSerialize(), $retrievedEntity->jsonSerialize());
    }

    /**
     * @return array<mixed>
     */
    public function retrieveDataProvider(): array
    {
        return [
            'without context' => [
                'entity' => new ActionFailure(
                    self::MACHINE_ID,
                    Code::UNKNOWN,
                    Reason::UNKNOWN
                ),
            ],
            'with context' => [
                'entity' => new ActionFailure(
                    self::MACHINE_ID,
                    Code::UNKNOWN,
                    Reason::UNKNOWN,
                    [
                        'key1' => 'value1',
                    ]
                ),
            ],
        ];
    }
}
