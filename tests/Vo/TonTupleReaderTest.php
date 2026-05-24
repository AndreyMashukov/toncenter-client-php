<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests\Vo;

use Amashukov\Toncenter\Vo\TonTupleItemBuilder;
use Amashukov\Toncenter\Vo\TonTupleItemCell;
use Amashukov\Toncenter\Vo\TonTupleItemInt;
use Amashukov\Toncenter\Vo\TonTupleItemNull;
use Amashukov\Toncenter\Vo\TonTupleItemSlice;
use Amashukov\Toncenter\Vo\TonTupleItemTuple;
use Amashukov\Toncenter\Vo\TonTupleReader;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TonTupleReaderTest extends TestCase
{
    public function testDecodesDecimalHexAndNegativeHexInts(): void
    {
        $reader = TonTupleReader::fromRawStack([
            ['num', '42'],
            ['int', '0x1a'],
            ['num', '-0x10'],
            ['num', 'ff'],
            ['int', ''],
        ]);

        self::assertSame(5, $reader->remaining());
        self::assertSame('42', $reader->readBigInt());
        self::assertSame('26', $reader->readBigInt());
        self::assertSame('-16', $reader->readBigInt());
        self::assertSame('255', $reader->readBigInt());
        self::assertSame('0', $reader->readBigInt());
        self::assertSame(0, $reader->remaining());
    }

    public function testReadNumberCastsToInt(): void
    {
        $reader = TonTupleReader::fromRawStack([['num', '0x100']]);

        self::assertSame(256, $reader->readNumber());
    }

    public function testDecodesCellSliceBuilderWithBytesEnvelope(): void
    {
        $reader = TonTupleReader::fromRawStack([
            ['cell', ['bytes' => 'CELL==']],
            ['slice', 'SLICE=='],
            ['builder', ['bytes' => 'BUILD==']],
        ]);

        self::assertSame('CELL==', $reader->readCellBoc());
        self::assertSame('SLICE==', $reader->readSliceBoc());

        $builder = $reader->pop();
        if (!$builder instanceof TonTupleItemBuilder) {
            self::fail('expected a builder item');
        }
        self::assertSame('BUILD==', $builder->boc);
    }

    public function testDecodesNullAndUnknownTypeAsNull(): void
    {
        $reader = TonTupleReader::fromRawStack([
            ['null', null],
            ['weird', 'x'],
        ]);

        self::assertSame(TonTupleItemNull::class, $reader->pop()::class);
        self::assertSame(TonTupleItemNull::class, $reader->pop()::class);
    }

    public function testDecodesNestedTuple(): void
    {
        $reader = TonTupleReader::fromRawStack([
            ['tuple', ['elements' => [['num', '7'], ['cell', ['bytes' => 'C==']]]]],
        ]);

        $tuple = $reader->pop();
        if (!$tuple instanceof TonTupleItemTuple) {
            self::fail('expected a tuple item');
        }
        self::assertCount(2, $tuple->items);

        $first = $tuple->items[0];
        if (!$first instanceof TonTupleItemInt) {
            self::fail('expected nested int');
        }
        self::assertSame('7', $first->value);

        $second = $tuple->items[1];
        if (!$second instanceof TonTupleItemCell) {
            self::fail('expected nested cell');
        }
        self::assertSame('C==', $second->boc);
    }

    public function testPeekDoesNotAdvanceCursor(): void
    {
        $reader = TonTupleReader::fromRawStack([['num', '1']]);

        $peeked = $reader->peek();
        if (!$peeked instanceof TonTupleItemInt) {
            self::fail('expected int');
        }
        self::assertSame('1', $peeked->value);
        self::assertSame(1, $reader->remaining());
    }

    public function testReadBigIntThrowsOnTypeMismatch(): void
    {
        $reader = TonTupleReader::fromRawStack([['cell', 'C==']]);

        $this->expectException(RuntimeException::class);
        $reader->readBigInt();
    }

    public function testPopPastEndThrows(): void
    {
        $reader = TonTupleReader::fromRawStack([]);

        $this->expectException(LogicException::class);
        $reader->pop();
    }
}
