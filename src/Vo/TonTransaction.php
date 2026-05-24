<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Vo;

final readonly class TonTransaction
{
    /**
     * @param list<TonTransactionMessage> $outMsgs
     */
    public function __construct(
        public string $hash,
        public TonTransactionStatus $status,
        public string $accountAddress,
        public int $lt,
        public int $utime,
        public ?int $totalFees,
        public TonComputePhase $computePhase,
        public ?TonActionPhase $actionPhase,
        public bool $aborted,
        public bool $destroyed,
        public string $oldStatus,
        public string $endStatus,
        public ?TonTransactionMessage $inMsg,
        public array $outMsgs,
    ) {}

    public function isStatusSuccess(): bool
    {
        return TonTransactionStatus::Success === $this->status;
    }

    public function isStatusFail(): bool
    {
        return !in_array($this->status, [TonTransactionStatus::Success, TonTransactionStatus::Pending], true);
    }

    public function isStatusPending(): bool
    {
        return TonTransactionStatus::Pending === $this->status;
    }

    /**
     * @param null|array<string, mixed> $row toncenter v2 transaction shape
     */
    public static function fromToncenter(?array $row, string $accountAddress = ''): self
    {
        if (null === $row) {
            return new self(
                hash: '',
                status: TonTransactionStatus::Pending,
                accountAddress: $accountAddress,
                lt: 0,
                utime: 0,
                totalFees: null,
                computePhase: new TonComputeSkipped(TonComputeSkipReason::NoState),
                actionPhase: null,
                aborted: false,
                destroyed: false,
                oldStatus: 'nonexist',
                endStatus: 'nonexist',
                inMsg: null,
                outMsgs: [],
            );
        }

        $txId         = (array) ($row['transaction_id'] ?? []);
        $hash         = Wire::str($txId['hash'] ?? null);
        $lt           = Wire::int($txId['lt'] ?? null);
        $utime        = Wire::int($row['utime'] ?? null);
        $totalFees    = self::nullableIntField($row, 'fee');
        $description  = (array) ($row['description'] ?? []);
        $computePhase = self::parseComputePhase((array) ($description['compute_ph'] ?? []));
        $actionPhase  = isset($description['action']) && is_array($description['action'])
            ? self::parseActionPhase($description['action'])
            : null;
        $aborted      = (bool) ($description['aborted'] ?? false);
        $destroyed    = (bool) ($description['destroyed'] ?? false);
        $oldStatus    = Wire::str($description['old_status'] ?? null, 'nonexist');
        $endStatus    = Wire::str($description['end_status'] ?? null, 'nonexist');
        $status       = self::deriveStatus($computePhase, $actionPhase, $aborted);

        $accountFromRow = $row['account'] ?? null;
        if (null === $accountFromRow) {
            $addressBlock   = (array) ($row['address'] ?? []);
            $accountFromRow = $addressBlock['account_address'] ?? null;
        }
        $address = '' !== $accountAddress ? $accountAddress : Wire::str($accountFromRow);

        $inMsg   = isset($row['in_msg']) && is_array($row['in_msg']) ? self::parseMessage($row['in_msg']) : null;
        $outMsgs = [];
        foreach ((array) ($row['out_msgs'] ?? []) as $msg) {
            if (is_array($msg)) {
                $outMsgs[] = self::parseMessage($msg);
            }
        }

        return new self(
            hash: $hash,
            status: $status,
            accountAddress: $address,
            lt: $lt,
            utime: $utime,
            totalFees: $totalFees,
            computePhase: $computePhase,
            actionPhase: $actionPhase,
            aborted: $aborted,
            destroyed: $destroyed,
            oldStatus: $oldStatus,
            endStatus: $endStatus,
            inMsg: $inMsg,
            outMsgs: $outMsgs,
        );
    }

    private static function deriveStatus(TonComputePhase $computePhase, ?TonActionPhase $actionPhase, bool $aborted): TonTransactionStatus
    {
        if ($aborted) {
            return TonTransactionStatus::Aborted;
        }
        if ($computePhase instanceof TonComputeVm && !$computePhase->success) {
            return TonTransactionStatus::ComputePhaseFailed;
        }
        if ($actionPhase instanceof TonActionPhase && !$actionPhase->success) {
            return TonTransactionStatus::ActionPhaseFailed;
        }

        return TonTransactionStatus::Success;
    }

    /**
     * @param array<array-key, mixed> $compute
     */
    private static function parseComputePhase(array $compute): TonComputePhase
    {
        $type = Wire::str($compute['type'] ?? null, 'skipped');
        if ('skipped' === $type) {
            $reason = TonComputeSkipReason::tryFrom(Wire::str($compute['reason'] ?? null, 'no_state'))
                ?? TonComputeSkipReason::NoState;

            return new TonComputeSkipped($reason);
        }

        return new TonComputeVm(
            success: (bool) ($compute['success'] ?? false),
            exitCode: Wire::int($compute['exit_code'] ?? null),
            exitArg: self::nullableIntField($compute, 'exit_arg'),
            vmSteps: Wire::int($compute['vm_steps'] ?? null),
            gasUsed: self::nullableStringField($compute, 'gas_used'),
            gasLimit: self::nullableStringField($compute, 'gas_limit'),
            gasFees: self::nullableStringField($compute, 'gas_fees'),
        );
    }

    /**
     * @param array<array-key, mixed> $action
     */
    private static function parseActionPhase(array $action): TonActionPhase
    {
        return new TonActionPhase(
            success: (bool) ($action['success'] ?? false),
            valid: (bool) ($action['valid'] ?? false),
            noFunds: (bool) ($action['no_funds'] ?? false),
            statusChange: Wire::str($action['status_change'] ?? null, 'unchanged'),
            totalFwdFees: self::nullableStringField($action, 'total_fwd_fees'),
            totalActionFees: self::nullableStringField($action, 'total_action_fees'),
            resultCode: Wire::int($action['result_code'] ?? null),
            resultArg: self::nullableIntField($action, 'result_arg'),
            totalActions: Wire::int($action['total_actions'] ?? null),
            specActions: Wire::int($action['spec_actions'] ?? null),
            skippedActions: Wire::int($action['skipped_actions'] ?? null),
            messagesCreated: Wire::int($action['messages_created'] ?? null),
        );
    }

    /**
     * @param array<array-key, mixed> $msg
     */
    private static function parseMessage(array $msg): TonTransactionMessage
    {
        $msgData = (array) ($msg['msg_data'] ?? []);

        return new TonTransactionMessage(
            source: self::nullableStringField($msg, 'source'),
            destination: self::nullableStringField($msg, 'destination'),
            value: Wire::str($msg['value'] ?? null, '0'),
            fwdFee: self::nullableStringField($msg, 'fwd_fee'),
            ihrFee: self::nullableStringField($msg, 'ihr_fee'),
            createdLt: self::nullableIntField($msg, 'created_lt'),
            bodyHash: self::nullableStringField($msg, 'body_hash'),
            bodyText: self::nullableStringField($msg, 'message') ?? self::nullableStringField($msgData, 'text'),
            bodyBoc: self::nullableStringField($msgData, 'body'),
            opcode: self::nullableIntField($msg, 'op'),
            bounced: (bool) ($msg['bounced'] ?? false),
        );
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private static function nullableStringField(array $row, string $key): ?string
    {
        if (!isset($row[$key])) {
            return null;
        }

        return Wire::str($row[$key]);
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private static function nullableIntField(array $row, string $key): ?int
    {
        if (!isset($row[$key])) {
            return null;
        }

        return Wire::int($row[$key]);
    }
}
