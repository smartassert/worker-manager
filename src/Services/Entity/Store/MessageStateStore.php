<?php

namespace App\Services\Entity\Store;

use App\Entity\MessageState;

class MessageStateStore extends AbstractEntityStore
{
    public function find(string $messageId): ?MessageState
    {
        $entity = $this->entityManager->find(MessageState::class, $messageId);

        return $entity instanceof MessageState ? $entity : null;
    }

    public function store(MessageState $entity): void
    {
        $this->doStore($entity);
    }

    public function remove(string $messageId): void
    {
        $entity = $this->find($messageId);
        if ($entity instanceof MessageState) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }

    public function count(): int
    {
        $repository = $this->entityManager->getRepository(MessageState::class);

        return count($repository->findAll());
    }
}
