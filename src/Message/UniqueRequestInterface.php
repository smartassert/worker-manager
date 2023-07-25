<?php

declare(strict_types=1);

namespace App\Message;

interface UniqueRequestInterface
{
    /**
     * @return non-empty-string
     */
    public function getUniqueId(): string;
}
