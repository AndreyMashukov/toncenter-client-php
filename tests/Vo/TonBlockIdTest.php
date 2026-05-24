<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests\Vo;

use Amashukov\Toncenter\Vo\TonBlockId;
use PHPUnit\Framework\TestCase;

final class TonBlockIdTest extends TestCase
{
    public function testFromArrayMapsEveryField(): void
    {
        $block = TonBlockId::fromArray([
            'workchain' => -1,
            'shard'     => '-9223372036854775808',
            'seqno'     => 41234567,
            'root_hash' => 'ROOT==',
            'file_hash' => 'FILE==',
        ]);

        self::assertSame(-1, $block->workchain);
        self::assertSame('-9223372036854775808', $block->shard);
        self::assertSame(41234567, $block->seqno);
        self::assertSame('ROOT==', $block->rootHash);
        self::assertSame('FILE==', $block->fileHash);
    }

    public function testFromArrayAppliesDefaultsOnEmptyInput(): void
    {
        $block = TonBlockId::fromArray([]);

        self::assertSame(0, $block->workchain);
        self::assertSame('', $block->shard);
        self::assertSame(0, $block->seqno);
        self::assertSame('', $block->rootHash);
        self::assertSame('', $block->fileHash);
    }
}
