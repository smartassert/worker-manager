<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CreateFailure implements \JsonSerializable
{
    public const CODE_UNKNOWN = 0;
    public const REASON_UNKNOWN = 'unknown';

    public const CODE_UNSUPPORTED_PROVIDER = 1;
    public const REASON_UNSUPPORTED_PROVIDER = 'unsupported provider';

    public const CODE_API_LIMIT_EXCEEDED = 2;
    public const REASON_API_LIMIT_EXCEEDED = 'api limit exceeded';

    public const CODE_API_AUTHENTICATION_FAILURE = 3;
    public const REASON_API_AUTHENTICATION_FAILURE = 'api authentication failure';

    public const CODE_CURL_ERROR = 4;
    public const REASON_CURL_ERROR = 'http transport error';

    public const CODE_HTTP_ERROR = 5;
    public const REASON_HTTP_ERROR = 'http application error';

    public const CODE_UNPROCESSABLE_REQUEST = 6;
    public const REASON_UNPROCESSABLE_REQUEST = 'unprocessable request';

    public const CODE_UNKNOWN_MACHINE_PROVIDER_ERROR = 7;
    public const REASON_UNKNOWN_MACHINE_PROVIDER_ERROR = 'unknown machine provider error';

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: MachineIdInterface::LENGTH)]
    private string $id;

    /**
     * @var self::CODE_*
     */
    #[ORM\Column(type: 'integer')]
    private int $code;

    /**
     * @var self::REASON_*
     */
    #[ORM\Column(type: 'text')]
    private string $reason;

    /**
     * @var array<string, int|string>
     */
    #[ORM\Column(type: 'simple_array', nullable: true)]
    private array $context = [];

    /**
     * @param self::CODE_*              $code
     * @param self::REASON_*            $reason
     * @param array<string, int|string> $context
     */
    public function __construct(string $machineId, int $code, string $reason, array $context = [])
    {
        $this->id = $machineId;
        $this->code = $code;
        $this->reason = $reason;
        $this->context = $context;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return array<string, array<string, int|string>|int|string>
     */
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'reason' => $this->reason,
            'context' => $this->context,
        ];
    }
}
