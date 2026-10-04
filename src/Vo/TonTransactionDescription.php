<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

use Amashukov\TonCell\Boc;
use Amashukov\TonCell\Slice;
use Amashukov\Toncenter\TonRpcException;
use Throwable;

final readonly class TonTransactionDescription
{
    private const int ORDINARY_TRANSACTION_TAG = 0b0000;

    private const int TRANSACTION_MAGIC = 0b0111;

    public function __construct(
        public ?string $credited,
        public TonComputePhase $computePhase,
        public ?TonActionPhase $actionPhase,
        public bool $aborted,
        public bool $bounced,
        public bool $destroyed,
    ) {}

    public static function fromTransactionBoc(string $dataBase64): ?self
    {
        try {
            return self::parse(Boc::decodeBase64($dataBase64)->beginParse());
        } catch (TonRpcException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new TonRpcException('Toncenter returned a transaction whose data does not parse as a TL-B Transaction', 0, $exception);
        }
    }

    private static function parse(Slice $transaction): ?self
    {
        if (self::TRANSACTION_MAGIC !== (int) $transaction->loadUint(4)) {
            throw new TonRpcException('Toncenter returned transaction data that is not a TL-B Transaction');
        }

        $transaction->loadBits(256);
        $transaction->loadUint(64);
        $transaction->loadBits(256);
        $transaction->loadUint(64);
        $transaction->loadUint(32);
        $transaction->loadUint(15);
        $transaction->loadUint(2);
        $transaction->loadUint(2);
        $transaction->loadRef();
        self::currencyCollection($transaction);
        $transaction->loadRef();

        $description = $transaction->loadRef()->beginParse();
        if (self::ORDINARY_TRANSACTION_TAG !== (int) $description->loadUint(4)) {
            return null;
        }

        self::bit($description);
        if (self::bit($description)) {
            $description->loadCoins();
            if (self::bit($description)) {
                $description->loadCoins();
            }
            self::accountStatusChange($description);
        }

        $credited = null;
        if (self::bit($description)) {
            if (self::bit($description)) {
                $description->loadCoins();
            }
            $credited = self::currencyCollection($description);
        }

        $computePhase = self::computePhase($description);
        $actionPhase  = self::bit($description) ? self::actionPhase($description->loadRef()->beginParse()) : null;
        $aborted      = self::bit($description);
        $bounced      = self::bit($description) && self::bounceMessageSent($description);
        $destroyed    = self::bit($description);

        return new self($credited, $computePhase, $actionPhase, $aborted, $bounced, $destroyed);
    }

    private static function computePhase(Slice $description): TonComputePhase
    {
        if (!self::bit($description)) {
            return new TonComputeSkipped(self::skipReason($description));
        }

        $success = self::bit($description);
        self::bit($description);
        self::bit($description);
        $gasFees = $description->loadCoins();

        $vm       = $description->loadRef()->beginParse();
        $gasUsed  = self::varUint($vm, 3);
        $gasLimit = self::varUint($vm, 3);
        if (self::bit($vm)) {
            self::varUint($vm, 2);
        }
        $vm->loadUint(8);
        $exitCode = self::int32($vm);
        $exitArg  = self::bit($vm) ? self::int32($vm) : null;
        $vmSteps  = (int) $vm->loadUint(32);

        return new TonComputeVm($success, $exitCode, $exitArg, $vmSteps, $gasUsed, $gasLimit, $gasFees);
    }

    private static function skipReason(Slice $description): TonComputeSkipReason
    {
        if (!self::bit($description)) {
            return self::bit($description) ? TonComputeSkipReason::BadState : TonComputeSkipReason::NoState;
        }
        if (!self::bit($description)) {
            return TonComputeSkipReason::NoGas;
        }
        if (self::bit($description)) {
            throw new TonRpcException('Toncenter returned a compute phase with an unknown skip reason');
        }

        return TonComputeSkipReason::Suspended;
    }

    private static function actionPhase(Slice $action): TonActionPhase
    {
        $success         = self::bit($action);
        $valid           = self::bit($action);
        $noFunds         = self::bit($action);
        $statusChange    = self::accountStatusChange($action);
        $totalFwdFees    = self::bit($action) ? $action->loadCoins() : null;
        $totalActionFees = self::bit($action) ? $action->loadCoins() : null;
        $resultCode      = self::int32($action);
        $resultArg       = self::bit($action) ? self::int32($action) : null;

        return new TonActionPhase(
            success: $success,
            valid: $valid,
            noFunds: $noFunds,
            statusChange: $statusChange,
            totalFwdFees: $totalFwdFees,
            totalActionFees: $totalActionFees,
            resultCode: $resultCode,
            resultArg: $resultArg,
            totalActions: (int) $action->loadUint(16),
            specActions: (int) $action->loadUint(16),
            skippedActions: (int) $action->loadUint(16),
            messagesCreated: (int) $action->loadUint(16),
        );
    }

    private static function bounceMessageSent(Slice $description): bool
    {
        if (self::bit($description)) {
            self::storageUsedShort($description);
            $description->loadCoins();
            $description->loadCoins();

            return true;
        }
        if (self::bit($description)) {
            self::storageUsedShort($description);
            $description->loadCoins();
        }

        return false;
    }

    /**
     * @phpstan-impure
     */
    private static function bit(Slice $slice): bool
    {
        return $slice->loadBit();
    }

    private static function storageUsedShort(Slice $slice): void
    {
        self::varUint($slice, 3);
        self::varUint($slice, 3);
    }

    private static function accountStatusChange(Slice $slice): string
    {
        if (!self::bit($slice)) {
            return 'unchanged';
        }

        return self::bit($slice) ? 'deleted' : 'frozen';
    }

    private static function currencyCollection(Slice $slice): string
    {
        $grams = $slice->loadCoins();
        if (self::bit($slice)) {
            $slice->loadRef();
        }

        return $grams;
    }

    private static function varUint(Slice $slice, int $lengthBits): string
    {
        $bytes = (int) $slice->loadUint($lengthBits);

        return 0 === $bytes ? '0' : $slice->loadUintString($bytes * 8);
    }

    private static function int32(Slice $slice): int
    {
        $unsigned = (int) $slice->loadUint(32);

        return $unsigned >= 0x80000000 ? $unsigned - 0x100000000 : $unsigned;
    }
}
