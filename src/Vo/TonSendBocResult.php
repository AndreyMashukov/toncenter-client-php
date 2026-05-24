<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

final readonly class TonSendBocResult
{
    public function __construct(public string $hash) {}

    /**
     * @param array<string, mixed> $row toncenter `/sendBoc` result envelope
     */
    public static function fromArray(array $row): self
    {
        return new self(hash: Wire::str($row['hash'] ?? null));
    }
}
