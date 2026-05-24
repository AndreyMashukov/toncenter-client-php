<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

final readonly class TonBlockId
{
    public function __construct(
        public int $workchain,
        public string $shard,
        public int $seqno,
        public string $rootHash,
        public string $fileHash,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            workchain: Wire::int($row['workchain'] ?? null),
            shard: Wire::str($row['shard'] ?? null),
            seqno: Wire::int($row['seqno'] ?? null),
            rootHash: Wire::str($row['root_hash'] ?? null),
            fileHash: Wire::str($row['file_hash'] ?? null),
        );
    }
}
