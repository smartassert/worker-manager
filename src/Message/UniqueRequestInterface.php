<?php

declare(strict_types=1);

namespace App\Message;

interface UniqueRequestInterface
{
    public function getUniqueId(): string;
}
