<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

final readonly class TonLastTransaction
{
    public function __construct(
        public int $lt,
        public string $hash,
    ) {}
}
