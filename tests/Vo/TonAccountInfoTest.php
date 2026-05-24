<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests\Vo;

use Amashukov\Toncenter\Vo\TonLastTransaction;
use Amashukov\Toncenter\Vo\TonAccountInfo;
use Amashukov\Toncenter\Vo\TonAccountState;
use PHPUnit\Framework\TestCase;

final class TonAccountInfoTest extends TestCase
{
    public function testActiveAccountWithFullPayload(): void
    {
        $info = TonAccountInfo::fromArray('EQAddr', [
            'state'               => 'active',
            'balance'             => '12500000000',
            'code'                => 'CODE==',
            'data'                => 'DATA==',
            'sync_utime'          => 1716500000,
            'last_transaction_id' => ['lt' => '4567', 'hash' => 'HASH=='],
        ]);

        self::assertSame('EQAddr', $info->address);
        self::assertSame(TonAccountState::Active, $info->state);
        self::assertTrue($info->isActive());
        self::assertSame('12500000000', $info->balance);
        self::assertSame('CODE==', $info->codeBoc);
        self::assertSame('DATA==', $info->dataBoc);
        self::assertSame(1716500000, $info->syncUtime);

        $lastTx = $info->lastTransaction;
        if (!$lastTx instanceof TonLastTransaction) {
            self::fail('expected a last transaction');
        }
        self::assertSame(4567, $lastTx->lt);
        self::assertSame('HASH==', $lastTx->hash);
    }

    public function testUninitializedAccountNormalizesEmptyFields(): void
    {
        $info = TonAccountInfo::fromArray('EQAddr', [
            'state'               => 'uninitialized',
            'balance'             => 'not-a-number',
            'code'                => '',
            'data'                => '',
            'last_transaction_id' => ['lt' => '0', 'hash' => ''],
        ]);

        self::assertTrue($info->isUninitialized());
        self::assertSame('0', $info->balance);
        self::assertNull($info->codeBoc);
        self::assertNull($info->dataBoc);
        self::assertNull($info->lastTransaction);
        self::assertNull($info->syncUtime);
    }

    public function testFrozenPredicate(): void
    {
        $info = TonAccountInfo::fromArray('EQAddr', ['state' => 'frozen', 'balance' => '0']);

        self::assertTrue($info->isFrozen());
        self::assertFalse($info->isActive());
    }
}
