<?php

declare(strict_types=1);

namespace App\Enum;

enum MessageHandlingReadiness: string
{
    case NEVER = 'never';
    case EVENTUALLY = 'eventually';
    case NOW = 'now';
}
