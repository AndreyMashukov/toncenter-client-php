<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

final readonly class TonActionPhase
{
    public function __construct(
        public bool $success,
        public bool $valid,
        public bool $noFunds,
        public string $statusChange,
        public ?string $totalFwdFees,
        public ?string $totalActionFees,
        public int $resultCode,
        public ?int $resultArg,
        public int $totalActions,
        public int $specActions,
        public int $skippedActions,
        public int $messagesCreated,
    ) {}
}
