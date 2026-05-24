<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests\Vo;

use Amashukov\Toncenter\Vo\TonAccountState;
use PHPUnit\Framework\TestCase;

final class TonAccountStateTest extends TestCase
{
    public function testBackingValues(): void
    {
        self::assertSame('active', TonAccountState::Active->value);
        self::assertSame('uninitialized', TonAccountState::Uninitialized->value);
        self::assertSame('frozen', TonAccountState::Frozen->value);
    }

    public function testFromWireMapsCanonicalAndAliasVocabulary(): void
    {
        self::assertSame(TonAccountState::Active, TonAccountState::fromWire('active'));
        self::assertSame(TonAccountState::Active, TonAccountState::fromWire('ACTIVE'));
        self::assertSame(TonAccountState::Frozen, TonAccountState::fromWire('frozen'));
        self::assertSame(TonAccountState::Uninitialized, TonAccountState::fromWire('uninit'));
        self::assertSame(TonAccountState::Uninitialized, TonAccountState::fromWire('nonexist'));
        self::assertSame(TonAccountState::Uninitialized, TonAccountState::fromWire(''));
        self::assertSame(TonAccountState::Uninitialized, TonAccountState::fromWire('something-unknown'));
    }
}
