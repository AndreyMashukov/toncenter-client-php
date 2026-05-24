<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests\Vo;

use Amashukov\Toncenter\Vo\TonTransactionMessage;
use Amashukov\Toncenter\Vo\TonComputeSkipped;
use Amashukov\Toncenter\Vo\TonComputeSkipReason;
use Amashukov\Toncenter\Vo\TonComputeVm;
use Amashukov\Toncenter\Vo\TonTransaction;
use Amashukov\Toncenter\Vo\TonTransactionStatus;
use PHPUnit\Framework\TestCase;

final class TonTransactionTest extends TestCase
{
    public function testNullRowIsPendingSentinel(): void
    {
        $tx = TonTransaction::fromToncenter(null, 'EQAccount');

        self::assertSame(TonTransactionStatus::Pending, $tx->status);
        self::assertTrue($tx->isStatusPending());
        self::assertFalse($tx->isStatusFail());
        self::assertSame('', $tx->hash);
        self::assertSame('EQAccount', $tx->accountAddress);
        self::assertInstanceOf(TonComputeSkipped::class, $tx->computePhase);
    }

    public function testSuccessfulVmTransaction(): void
    {
        $tx = TonTransaction::fromToncenter($this->row([
            'compute_ph' => ['type' => 'vm', 'success' => true, 'exit_code' => 0, 'vm_steps' => 30],
            'action'     => ['success' => true, 'valid' => true],
            'aborted'    => false,
        ]), 'EQAccount');

        self::assertSame(TonTransactionStatus::Success, $tx->status);
        self::assertTrue($tx->isStatusSuccess());

        $compute = $tx->computePhase;
        if (!$compute instanceof TonComputeVm) {
            self::fail('expected a VM compute phase');
        }
        self::assertSame(30, $compute->vmSteps);
        self::assertSame('abc123', $tx->hash);
        self::assertSame(4567, $tx->lt);
    }

    public function testComputePhaseFailed(): void
    {
        $tx = TonTransaction::fromToncenter($this->row([
            'compute_ph' => ['type' => 'vm', 'success' => false, 'exit_code' => 32],
            'aborted'    => false,
        ]));

        self::assertSame(TonTransactionStatus::ComputePhaseFailed, $tx->status);
        self::assertTrue($tx->isStatusFail());
    }

    public function testActionPhaseFailed(): void
    {
        $tx = TonTransaction::fromToncenter($this->row([
            'compute_ph' => ['type' => 'vm', 'success' => true, 'exit_code' => 0],
            'action'     => ['success' => false, 'result_code' => 37],
            'aborted'    => false,
        ]));

        self::assertSame(TonTransactionStatus::ActionPhaseFailed, $tx->status);
    }

    public function testAbortedWins(): void
    {
        $tx = TonTransaction::fromToncenter($this->row([
            'compute_ph' => ['type' => 'vm', 'success' => true, 'exit_code' => 0],
            'action'     => ['success' => true],
            'aborted'    => true,
        ]));

        self::assertSame(TonTransactionStatus::Aborted, $tx->status);
    }

    public function testSkippedComputePhaseReason(): void
    {
        $tx = TonTransaction::fromToncenter($this->row([
            'compute_ph' => ['type' => 'skipped', 'reason' => 'no_gas'],
            'aborted'    => false,
        ]));

        $compute = $tx->computePhase;
        if (!$compute instanceof TonComputeSkipped) {
            self::fail('expected a skipped compute phase');
        }
        self::assertSame(TonComputeSkipReason::NoGas, $compute->reason);
        self::assertSame(TonTransactionStatus::Success, $tx->status);
    }

    public function testParsesInAndOutMessages(): void
    {
        $tx = TonTransaction::fromToncenter($this->row([
            'compute_ph' => ['type' => 'vm', 'success' => true, 'exit_code' => 0],
            'aborted'    => false,
        ], [
            'in_msg'   => ['source' => '', 'destination' => 'EQAccount', 'value' => '1000000000', 'message' => 'memo'],
            'out_msgs' => [
                ['source' => 'EQAccount', 'destination' => 'EQDest', 'value' => '900000000', 'op' => 0, 'bounced' => false],
                'not-an-array',
            ],
        ]));

        $inMsg = $tx->inMsg;
        if (!$inMsg instanceof TonTransactionMessage) {
            self::fail('expected an in message');
        }
        self::assertSame('', $inMsg->source);
        self::assertSame('memo', $inMsg->bodyText);

        self::assertCount(1, $tx->outMsgs);
        self::assertSame('EQDest', $tx->outMsgs[0]->destination);
        self::assertSame('900000000', $tx->outMsgs[0]->value);
    }

    public function testAccountAddressFallsBackToRowWhenArgEmpty(): void
    {
        $tx = TonTransaction::fromToncenter($this->row([
            'compute_ph' => ['type' => 'vm', 'success' => true, 'exit_code' => 0],
            'aborted'    => false,
        ], ['account' => 'EQFromRow']), '');

        self::assertSame('EQFromRow', $tx->accountAddress);
    }

    /**
     * @param array<string, mixed> $description
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function row(array $description, array $extra = []): array
    {
        return array_merge([
            'transaction_id' => ['hash' => 'abc123', 'lt' => '4567'],
            'utime'          => 1716500000,
            'fee'            => '266669',
            'description'    => $description,
        ], $extra);
    }
}
