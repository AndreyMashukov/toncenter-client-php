<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

final readonly class TonAccountInfo
{
    /**
     * @param numeric-string $balance decimal nano-TON string (bigint-safe — TON supply exceeds PHP_INT_MAX past ~9,223 TON)
     */
    public function __construct(
        public string $address,
        public TonAccountState $state,
        public string $balance,
        public ?string $codeBoc,
        public ?string $dataBoc,
        public ?TonLastTransaction $lastTransaction,
        public ?int $syncUtime,
    ) {}

    public function isActive(): bool
    {
        return TonAccountState::Active === $this->state;
    }

    public function isUninitialized(): bool
    {
        return TonAccountState::Uninitialized === $this->state;
    }

    public function isFrozen(): bool
    {
        return TonAccountState::Frozen === $this->state;
    }

    /**
     * @param array<string, mixed> $row toncenter `/getAddressInformation` envelope
     */
    public static function fromArray(string $address, array $row): self
    {
        $balanceRaw = $row['balance'] ?? '0';
        $balance    = is_numeric($balanceRaw) ? (string) $balanceRaw : '0';

        $lastTx = null;
        $lastId = $row['last_transaction_id'] ?? null;
        if (is_array($lastId)) {
            $lt = Wire::int($lastId['lt'] ?? null);
            if ($lt > 0) {
                $lastTx = new TonLastTransaction(lt: $lt, hash: Wire::str($lastId['hash'] ?? null));
            }
        }

        $code = isset($row['code']) ? Wire::str($row['code']) : null;
        $data = isset($row['data']) ? Wire::str($row['data']) : null;

        $syncUtimeRaw = $row['sync_utime'] ?? null;
        $syncUtime    = is_numeric($syncUtimeRaw) ? Wire::int($syncUtimeRaw) : null;

        return new self(
            address: $address,
            state: TonAccountState::fromWire(Wire::str($row['state'] ?? null)),
            balance: $balance,
            codeBoc: '' === $code ? null : $code,
            dataBoc: '' === $data ? null : $data,
            lastTransaction: $lastTx,
            syncUtime: $syncUtime,
        );
    }
}
