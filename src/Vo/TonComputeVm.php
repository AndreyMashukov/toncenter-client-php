<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

final readonly class TonComputeVm extends TonComputePhase
{
    public function __construct(
        public bool $success,
        public int $exitCode,
        public ?int $exitArg,
        public int $vmSteps,
        public ?string $gasUsed,
        public ?string $gasLimit,
        public ?string $gasFees,
    ) {}
}
