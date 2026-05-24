<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

final readonly class TonMasterchainInfo
{
    public function __construct(
        public TonBlockId $last,
        public TonBlockId $init,
        public string $stateRootHash,
    ) {}

    /**
     * @param array<string, mixed> $envelope toncenter `/getMasterchainInfo` `result` body
     */
    public static function fromToncenter(array $envelope): self
    {
        $last = $envelope['last'] ?? [];
        $init = $envelope['init'] ?? [];

        return new self(
            last: TonBlockId::fromArray(is_array($last) ? $last : []),
            init: TonBlockId::fromArray(is_array($init) ? $init : []),
            stateRootHash: Wire::str($envelope['state_root_hash'] ?? null),
        );
    }
}
