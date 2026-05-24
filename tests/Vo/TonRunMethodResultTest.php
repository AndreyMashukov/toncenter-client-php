<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests\Vo;

use Amashukov\Toncenter\Vo\TonRunMethodResult;
use PHPUnit\Framework\TestCase;

final class TonRunMethodResultTest extends TestCase
{
    public function testParsesEnvelopeAndStack(): void
    {
        $result = TonRunMethodResult::fromToncenter([
            'ok'        => true,
            'exit_code' => 0,
            'gas_used'  => 1337,
            'stack'     => [['num', '0x2a']],
        ]);

        self::assertTrue($result->ok);
        self::assertSame(0, $result->exitCode);
        self::assertSame(1337, $result->gasUsed);
        self::assertTrue($result->isOk());
        self::assertSame(42, $result->stack->readNumber());
    }

    public function testIsOkFalseWhenExitCodeNonZero(): void
    {
        $result = TonRunMethodResult::fromToncenter([
            'ok'        => true,
            'exit_code' => 4,
            'gas_used'  => 0,
            'stack'     => [],
        ]);

        self::assertFalse($result->isOk());
    }

    public function testDefaultsAndMalformedStackRowsSkipped(): void
    {
        $result = TonRunMethodResult::fromToncenter([
            'stack' => [['num', '1'], ['only-one-element'], 'not-an-array'],
        ]);

        self::assertFalse($result->ok);
        self::assertSame(-1, $result->exitCode);
        self::assertSame(0, $result->gasUsed);
        self::assertSame(1, $result->stack->remaining());
    }
}
