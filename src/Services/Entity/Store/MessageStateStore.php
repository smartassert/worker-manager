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

    public function remove(MessageState $messageState): void
    {
        $this->entityManager->remove($messageState);
        $this->entityManager->flush();
    }
}
