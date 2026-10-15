<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests\Vo;

use Amashukov\Toncenter\TonRpcException;
use Amashukov\Toncenter\Vo\TonFeeBreakdown;
use Amashukov\Toncenter\Vo\TonFeeEstimate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TonFeeEstimateTest extends TestCase
{
    public function testPreservesComponentsAndDestinationOrder(): void
    {
        $fee = ['in_fwd_fee' => 0, 'storage_fee' => '00002', 'gas_fee' => '9223372036854775808', 'fwd_fee' => 7];
        $result = TonFeeEstimate::fromToncenter(['source_fees' => $fee, 'destination_fees' => [$fee, array_replace($fee, ['gas_fee' => 9])]]);
        self::assertSame('0', $result->sourceFees->inForwardFee);
        self::assertSame('2', $result->sourceFees->storageFee);
        self::assertSame('9223372036854775808', $result->sourceFees->gasFee);
        self::assertSame('7', $result->sourceFees->forwardFee);
        self::assertCount(2, $result->destinationFees);
        self::assertSame('9223372036854775808', $result->destinationFees[0]->gasFee);
        self::assertSame('9', $result->destinationFees[1]->gasFee);
    }

    public function testAcceptsExplicitZeroAndPreservesEmptyDestinationCoverage(): void
    {
        $result = TonFeeEstimate::fromToncenter(['source_fees' => ['in_fwd_fee' => 0, 'storage_fee' => 0, 'gas_fee' => 0, 'fwd_fee' => 0], 'destination_fees' => []]);
        self::assertSame('0', $result->sourceFees->gasFee);
        self::assertSame([], $result->destinationFees);
    }

    #[DataProvider('invalidComponents')]
    public function testRejectsInvalidAmounts(mixed $value): void
    {
        $this->expectException(TonRpcException::class);
        TonFeeBreakdown::fromToncenter(['in_fwd_fee' => 0, 'storage_fee' => 0, 'gas_fee' => $value, 'fwd_fee' => 0]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidComponents(): iterable
    {
        yield 'missing' => [null];
        yield 'negative integer' => [-1];
        yield 'negative string' => ['-1'];
        yield 'float' => [1.0];
        yield 'fraction' => ['0.1'];
        yield 'exponent' => ['1e3'];
        yield 'boolean' => [false];
        yield 'empty' => [''];
        yield 'whitespace' => [' 1'];
        yield 'array' => [[]];
        yield 'trailing newline' => ["1\n"];
    }

    #[DataProvider('invalidEstimates')]
    public function testRejectsIncompleteEstimates(mixed $row): void
    {
        $this->expectException(TonRpcException::class);
        TonFeeEstimate::fromToncenter($row);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidEstimates(): iterable
    {
        $zero = ['in_fwd_fee' => 0, 'storage_fee' => 0, 'gas_fee' => 0, 'fwd_fee' => 0];
        yield 'not object' => [false];
        yield 'missing source' => [['destination_fees' => []]];
        yield 'missing destinations' => [['source_fees' => $zero]];
        yield 'null destinations' => [['source_fees' => $zero, 'destination_fees' => null]];
        yield 'destinations not list' => [['source_fees' => $zero, 'destination_fees' => ['unexpected' => $zero]]];
        yield 'incomplete source' => [['source_fees' => ['gas_fee' => 1], 'destination_fees' => []]];
        yield 'invalid destination' => [['source_fees' => $zero, 'destination_fees' => [null]]];
        yield 'source not object' => [['source_fees' => 1, 'destination_fees' => []]];
    }
}
