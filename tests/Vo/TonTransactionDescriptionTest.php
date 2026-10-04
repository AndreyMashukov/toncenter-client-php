<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests\Vo;

use Amashukov\TonCell\Boc;
use Amashukov\TonCell\Builder;
use Amashukov\Toncenter\Tests\Support\TransactionCellBuilder;
use Amashukov\Toncenter\TonRpcException;
use Amashukov\Toncenter\Vo\TonComputeSkipped;
use Amashukov\Toncenter\Vo\TonComputeSkipReason;
use Amashukov\Toncenter\Vo\TonComputeVm;
use Amashukov\Toncenter\Vo\TonTransaction;
use Amashukov\Toncenter\Vo\TonTransactionDescription;
use Amashukov\Toncenter\Vo\TonTransactionStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TonTransactionDescriptionTest extends TestCase
{
    public function testASuccessfulTransactionReadsEveryPhase(): void
    {
        $description = TonTransactionDescription::fromTransactionBoc((new TransactionCellBuilder())->credit('160000000')->base64());

        self::assertInstanceOf(TonTransactionDescription::class, $description);
        self::assertSame('160000000', $description->credited);
        self::assertInstanceOf(TonComputeVm::class, $description->computePhase);
        self::assertTrue($description->computePhase->success);
        self::assertSame(0, $description->computePhase->exitCode);
        self::assertSame(77, $description->computePhase->vmSteps);
        self::assertSame('3000', $description->computePhase->gasUsed);
        self::assertSame('1000000', $description->computePhase->gasLimit);
        self::assertSame('2000000', $description->computePhase->gasFees);
        self::assertNotNull($description->actionPhase);
        self::assertTrue($description->actionPhase->success);
        self::assertSame('400000', $description->actionPhase->totalFwdFees);
        self::assertSame(1, $description->actionPhase->messagesCreated);
        self::assertFalse($description->aborted);
        self::assertFalse($description->bounced);
        self::assertFalse($description->destroyed);
    }

    public function testAFailedComputeThatBouncedReadsAsAbortedAndBounced(): void
    {
        $description = TonTransactionDescription::fromTransactionBoc((new TransactionCellBuilder())->credit('10000000')->computeFailed(-14)->withoutAction()->aborted()->bounce('ok')->base64());

        self::assertNotNull($description);
        self::assertInstanceOf(TonComputeVm::class, $description->computePhase);
        self::assertFalse($description->computePhase->success);
        self::assertSame(-14, $description->computePhase->exitCode);
        self::assertNull($description->actionPhase);
        self::assertTrue($description->aborted);
        self::assertTrue($description->bounced);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideABouncePhaseThatSentNothingIsNotABounceCases(): iterable
    {
        yield 'no funds for the bounce' => ['nofunds', false];
        yield 'negative funds' => ['negfunds', false];
        yield 'bounce sent' => ['ok', true];
    }

    #[DataProvider('provideABouncePhaseThatSentNothingIsNotABounceCases')]
    public function testABouncePhaseThatSentNothingIsNotABounce(string $kind, bool $bounced): void
    {
        $description = TonTransactionDescription::fromTransactionBoc((new TransactionCellBuilder())->computeFailed(9)->withoutAction()->aborted()->bounce($kind)->base64());

        self::assertNotNull($description);
        self::assertSame($bounced, $description->bounced);
    }

    /**
     * @return iterable<string, array{string, TonComputeSkipReason}>
     */
    public static function provideASkippedComputeNamesItsReasonCases(): iterable
    {
        yield 'no state' => ['00', TonComputeSkipReason::NoState];
        yield 'bad state' => ['01', TonComputeSkipReason::BadState];
        yield 'no gas' => ['10', TonComputeSkipReason::NoGas];
        yield 'suspended' => ['110', TonComputeSkipReason::Suspended];
    }

    #[DataProvider('provideASkippedComputeNamesItsReasonCases')]
    public function testASkippedComputeNamesItsReason(string $bits, TonComputeSkipReason $reason): void
    {
        $description = TonTransactionDescription::fromTransactionBoc((new TransactionCellBuilder())->computeSkipped($bits)->withoutAction()->aborted()->base64());

        self::assertNotNull($description);
        self::assertInstanceOf(TonComputeSkipped::class, $description->computePhase);
        self::assertSame($reason, $description->computePhase->reason);
        self::assertSame('1000000000', $description->credited);
    }

    public function testATransferWithoutACreditPhaseCreditsNothing(): void
    {
        $description = TonTransactionDescription::fromTransactionBoc((new TransactionCellBuilder())->credit(null)->base64());

        self::assertNotNull($description);
        self::assertNull($description->credited);
    }

    public function testDataThatIsNotATransactionFailsLoud(): void
    {
        $this->expectException(TonRpcException::class);

        TonTransactionDescription::fromTransactionBoc(Boc::encodeBase64((new Builder())->storeUint(0b1111, 4)->endCell()));
    }

    public function testDataThatIsNotABocFailsLoud(): void
    {
        $this->expectException(TonRpcException::class);

        TonTransactionDescription::fromTransactionBoc('not-a-boc');
    }

    public function testAToncenterRowWithoutDescriptionTakesItsPhasesFromTheData(): void
    {
        $failed = TonTransaction::fromToncenter($this->row((new TransactionCellBuilder())->computeFailed(-14)->withoutAction()->aborted()->bounce('ok')->base64()), 'EQRecipient');
        $paid   = TonTransaction::fromToncenter($this->row((new TransactionCellBuilder())->base64()), 'EQRecipient');

        self::assertSame(TonTransactionStatus::Aborted, $failed->status);
        self::assertNotNull($failed->description);
        self::assertTrue($failed->description->bounced);
        self::assertSame(TonTransactionStatus::Success, $paid->status);
        self::assertNotNull($paid->description);
        self::assertSame('1000000000', $paid->description->credited);
    }

    public function testARowWithoutDataKeepsTheDefaultsAndNoDescription(): void
    {
        $row = $this->row('');
        unset($row['data']);

        $transaction = TonTransaction::fromToncenter($row, 'EQRecipient');

        self::assertNull($transaction->description);
        self::assertSame(TonTransactionStatus::Success, $transaction->status);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $data): array
    {
        return [
            'utime'          => 1_700_000_000,
            'data'           => $data,
            'transaction_id' => ['lt' => '1000000', 'hash' => 'aGFzaA=='],
            'fee'            => '5000000',
            'in_msg'         => null,
            'out_msgs'       => [],
        ];
    }
}
