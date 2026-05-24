<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests\Vo;

use Amashukov\Toncenter\Vo\TonTransactionStatus;
use PHPUnit\Framework\TestCase;

final class TonTransactionStatusTest extends TestCase
{
    public function testFiveDistinctCases(): void
    {
        $cases = TonTransactionStatus::cases();

        self::assertCount(5, $cases);
        self::assertSame(
            ['Pending', 'Success', 'ComputePhaseFailed', 'ActionPhaseFailed', 'Aborted'],
            array_map(static fn(TonTransactionStatus $c): string => $c->name, $cases),
        );
    }
}
