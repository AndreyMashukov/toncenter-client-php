<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests\Vo;

use Amashukov\Toncenter\Vo\TonComputeSkipped;
use Amashukov\Toncenter\Vo\TonComputeSkipReason;
use Amashukov\Toncenter\Vo\TonComputeVm;
use PHPUnit\Framework\TestCase;

final class TonComputePhaseTest extends TestCase
{
    public function testSkippedCarriesReason(): void
    {
        $phase = new TonComputeSkipped(TonComputeSkipReason::NoState);

        self::assertSame(TonComputeSkipReason::NoState, $phase->reason);
    }

    public function testVmCarriesEveryField(): void
    {
        $phase = new TonComputeVm(
            success: true,
            exitCode: 0,
            exitArg: null,
            vmSteps: 21,
            gasUsed: '309',
            gasLimit: '1000000',
            gasFees: '123456',
        );

        self::assertTrue($phase->success);
        self::assertSame(0, $phase->exitCode);
        self::assertNull($phase->exitArg);
        self::assertSame(21, $phase->vmSteps);
        self::assertSame('309', $phase->gasUsed);
        self::assertSame('1000000', $phase->gasLimit);
        self::assertSame('123456', $phase->gasFees);
    }

    public function testSkipReasonVocabulary(): void
    {
        self::assertSame('no_state', TonComputeSkipReason::NoState->value);
        self::assertSame('bad_state', TonComputeSkipReason::BadState->value);
        self::assertSame('no_gas', TonComputeSkipReason::NoGas->value);
        self::assertSame('suspended', TonComputeSkipReason::Suspended->value);
    }
}
