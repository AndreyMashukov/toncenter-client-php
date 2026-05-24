<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

final readonly class TonTransactionMessage
{
    public function __construct(
        public ?string $source,
        public ?string $destination,
        public string $value,
        public ?string $fwdFee,
        public ?string $ihrFee,
        public ?int $createdLt,
        public ?string $bodyHash,
        public ?string $bodyText,
        public ?string $bodyBoc,
        public ?int $opcode,
        public bool $bounced = false,
    ) {}
}
