<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests\Vo;

use Amashukov\Toncenter\Vo\TonTransactionMessage;
use PHPUnit\Framework\TestCase;

final class TonTransactionMessageTest extends TestCase
{
    public function testFullInternalMessage(): void
    {
        $msg = new TonTransactionMessage(
            source: 'EQSource',
            destination: 'EQDest',
            value: '500000000',
            fwdFee: '266669',
            ihrFee: '0',
            createdLt: 4567,
            bodyHash: 'BODY==',
            bodyText: 'memo-text',
            bodyBoc: 'BOC==',
            opcode: 0xA8023300,
            bounced: true,
        );

        self::assertSame('EQSource', $msg->source);
        self::assertSame('EQDest', $msg->destination);
        self::assertSame('500000000', $msg->value);
        self::assertSame('266669', $msg->fwdFee);
        self::assertSame('0', $msg->ihrFee);
        self::assertSame(4567, $msg->createdLt);
        self::assertSame('BODY==', $msg->bodyHash);
        self::assertSame('memo-text', $msg->bodyText);
        self::assertSame('BOC==', $msg->bodyBoc);
        self::assertSame(0xA8023300, $msg->opcode);
        self::assertTrue($msg->bounced);
    }

    public function testExternalInMessageDefaultsBounced(): void
    {
        $msg = new TonTransactionMessage(
            source: null,
            destination: 'EQDest',
            value: '0',
            fwdFee: null,
            ihrFee: null,
            createdLt: null,
            bodyHash: null,
            bodyText: null,
            bodyBoc: null,
            opcode: null,
        );

        self::assertNull($msg->source);
        self::assertFalse($msg->bounced);
    }
}
