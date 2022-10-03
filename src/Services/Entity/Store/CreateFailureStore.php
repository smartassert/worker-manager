<?php

namespace App\Services\Entity\Store;

use App\Entity\CreateFailure;

class CreateFailureStore extends AbstractEntityStore
{
    public function store(CreateFailure $entity): void
    {
        $this->doStore($entity);
    }
}
