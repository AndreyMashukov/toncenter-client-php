<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests\Vo;

use Amashukov\Toncenter\Vo\TonActionPhase;
use PHPUnit\Framework\TestCase;

final class TonActionPhaseTest extends TestCase
{
    public function testEveryFieldRoundTrips(): void
    {
        $phase = new TonActionPhase(
            success: false,
            valid: true,
            noFunds: true,
            statusChange: 'frozen',
            totalFwdFees: '5000',
            totalActionFees: '2500',
            resultCode: 37,
            resultArg: -2,
            totalActions: 3,
            specActions: 1,
            skippedActions: 2,
            messagesCreated: 1,
        );

        self::assertFalse($phase->success);
        self::assertTrue($phase->valid);
        self::assertTrue($phase->noFunds);
        self::assertSame('frozen', $phase->statusChange);
        self::assertSame('5000', $phase->totalFwdFees);
        self::assertSame('2500', $phase->totalActionFees);
        self::assertSame(37, $phase->resultCode);
        self::assertSame(-2, $phase->resultArg);
        self::assertSame(3, $phase->totalActions);
        self::assertSame(1, $phase->specActions);
        self::assertSame(2, $phase->skippedActions);
        self::assertSame(1, $phase->messagesCreated);
    }
}
