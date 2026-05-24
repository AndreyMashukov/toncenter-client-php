<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests\Vo;

use Amashukov\Toncenter\Vo\TonMasterchainInfo;
use PHPUnit\Framework\TestCase;

final class TonMasterchainInfoTest extends TestCase
{
    public function testFromToncenterLiftsNestedBlocks(): void
    {
        $info = TonMasterchainInfo::fromToncenter([
            'last'            => ['workchain' => -1, 'seqno' => 99],
            'init'            => ['workchain' => -1, 'seqno' => 0],
            'state_root_hash' => 'STATE==',
        ]);

        self::assertSame(99, $info->last->seqno);
        self::assertSame(0, $info->init->seqno);
        self::assertSame(-1, $info->last->workchain);
        self::assertSame('STATE==', $info->stateRootHash);
    }

    public function testFromToncenterToleratesMissingBlocks(): void
    {
        $info = TonMasterchainInfo::fromToncenter([]);

        self::assertSame(0, $info->last->seqno);
        self::assertSame(0, $info->init->seqno);
        self::assertSame('', $info->stateRootHash);
    }
}
