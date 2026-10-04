<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests\Support;

use Amashukov\TonCell\Boc;
use Amashukov\TonCell\Builder;
use Amashukov\TonCell\Cell;

final class TransactionCellBuilder
{
    private ?string $credit = '1000000000';

    private ?bool $computeSuccess = true;

    private string $skipReasonBits = '00';

    private ?bool $actionSuccess = true;

    private bool $aborted = false;

    private ?string $bounce = null;

    private int $exitCode = 0;

    public function credit(?string $nanotons): self
    {
        $this->credit = $nanotons;

        return $this;
    }

    public function computeSkipped(string $reasonBits): self
    {
        $this->computeSuccess = null;
        $this->skipReasonBits = $reasonBits;

        return $this;
    }

    public function computeFailed(int $exitCode): self
    {
        $this->computeSuccess = false;
        $this->exitCode       = $exitCode;

        return $this;
    }

    public function withoutAction(): self
    {
        $this->actionSuccess = null;

        return $this;
    }

    public function actionFailed(): self
    {
        $this->actionSuccess = false;

        return $this;
    }

    public function aborted(): self
    {
        $this->aborted = true;

        return $this;
    }

    public function bounce(string $kind): self
    {
        $this->bounce = $kind;

        return $this;
    }

    public function base64(): string
    {
        return Boc::encodeBase64($this->transaction());
    }

    private function transaction(): Cell
    {
        return (new Builder())
            ->storeUint(0b0111, 4)
            ->storeUint(0, 256)
            ->storeUint(1_000_000, 64)
            ->storeUint(0, 256)
            ->storeUint(999_999, 64)
            ->storeUint(1_700_000_000, 32)
            ->storeUint(0, 15)
            ->storeUint(0b10, 2)
            ->storeUint(0b10, 2)
            ->storeRef((new Builder())->storeBit(false)->storeBit(false)->endCell())
            ->storeCoins('5000000')
            ->storeBit(false)
            ->storeRef((new Builder())->storeUint(0x72, 8)->storeUint(0, 256)->storeUint(0, 256)->endCell())
            ->storeRef($this->description())
            ->endCell();
    }

    private function description(): Cell
    {
        $description = (new Builder())
            ->storeUint(0b0000, 4)
            ->storeBit(false)
            ->storeBit(true)
            ->storeCoins('1000')
            ->storeBit(false)
            ->storeBit(false);

        if (null === $this->credit) {
            $description->storeBit(false);
        } else {
            $description->storeBit(true)->storeBit(false)->storeCoins($this->credit)->storeBit(false);
        }

        if (null === $this->computeSuccess) {
            $description->storeBit(false);
            foreach (str_split($this->skipReasonBits) as $bit) {
                $description->storeBit('1' === $bit);
            }
        } else {
            $description
                ->storeBit(true)
                ->storeBit($this->computeSuccess)
                ->storeBit(false)
                ->storeBit(false)
                ->storeCoins('2000000')
                ->storeRef((new Builder())
                    ->storeUint(2, 3)->storeUint(3000, 16)
                    ->storeUint(3, 3)->storeUint(1_000_000, 24)
                    ->storeBit(false)
                    ->storeUint(0, 8)
                    ->storeInt($this->exitCode, 32)
                    ->storeBit(false)
                    ->storeUint(77, 32)
                    ->storeUint(0, 256)
                    ->storeUint(0, 256)
                    ->endCell());
        }

        if (null === $this->actionSuccess) {
            $description->storeBit(false);
        } else {
            $description->storeBit(true)->storeRef((new Builder())
                ->storeBit($this->actionSuccess)
                ->storeBit(true)
                ->storeBit(false)
                ->storeBit(false)
                ->storeBit(true)->storeCoins('400000')
                ->storeBit(false)
                ->storeInt($this->actionSuccess ? 0 : 37, 32)
                ->storeBit(false)
                ->storeUint(1, 16)
                ->storeUint(0, 16)
                ->storeUint(0, 16)
                ->storeUint(1, 16)
                ->storeUint(0, 256)
                ->storeUint(1, 3)->storeUint(1, 8)
                ->storeUint(2, 3)->storeUint(500, 16)
                ->endCell());
        }

        $description->storeBit($this->aborted);

        match ($this->bounce) {
            null      => $description->storeBit(false),
            'ok'      => $description->storeBit(true)->storeBit(true)->storeUint(1, 3)->storeUint(1, 8)->storeUint(1, 3)->storeUint(200, 8)->storeCoins('100')->storeCoins('200'),
            'nofunds' => $description->storeBit(true)->storeBit(false)->storeBit(true)->storeUint(1, 3)->storeUint(1, 8)->storeUint(1, 3)->storeUint(200, 8)->storeCoins('300'),
            default   => $description->storeBit(true)->storeBit(false)->storeBit(false),
        };

        return $description->storeBit(false)->endCell();
    }
}
