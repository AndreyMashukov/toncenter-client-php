<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

final readonly class TonComputeSkipped extends TonComputePhase
{
    public function __construct(
        public TonComputeSkipReason $reason,
    ) {}
}
