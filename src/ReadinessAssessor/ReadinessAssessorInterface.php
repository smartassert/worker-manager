<?php

declare(strict_types=1);

namespace App\ReadinessAssessor;

use App\Enum\MessageHandlingReadiness;

interface ReadinessAssessorInterface
{
    public function isReady(string $jobId): MessageHandlingReadiness;
}
