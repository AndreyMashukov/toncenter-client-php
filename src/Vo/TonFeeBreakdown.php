<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

use Amashukov\Toncenter\TonRpcException;

final readonly class TonFeeBreakdown
{
    private function __construct(
        public string $inForwardFee,
        public string $storageFee,
        public string $gasFee,
        public string $forwardFee,
    ) {}

    public static function fromToncenter(mixed $row): self
    {
        if (!is_array($row)) {
            throw new TonRpcException('A TON fee breakdown must be an object.');
        }

        return new self(
            self::nanotons($row['in_fwd_fee'] ?? null),
            self::nanotons($row['storage_fee'] ?? null),
            self::nanotons($row['gas_fee'] ?? null),
            self::nanotons($row['fwd_fee'] ?? null),
        );
    }

    private static function nanotons(mixed $value): string
    {
        if ((!is_int($value) && !is_string($value)) || 1 !== preg_match('/^[0-9]+$/D', (string) $value)) {
            throw new TonRpcException('A TON fee component must be a nonnegative integer number of nanotons.');
        }
        $normalized = ltrim((string) $value, '0');

        return '' === $normalized ? '0' : $normalized;
    }
}
