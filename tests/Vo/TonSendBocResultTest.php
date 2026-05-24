<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests\Vo;

use Amashukov\Toncenter\Vo\TonSendBocResult;
use PHPUnit\Framework\TestCase;

final class TonSendBocResultTest extends TestCase
{
    public function testFromArrayCapturesHash(): void
    {
        self::assertSame('BROADCAST==', TonSendBocResult::fromArray(['hash' => 'BROADCAST=='])->hash);
    }

    public function testFromArrayDefaultsToEmptyHash(): void
    {
        self::assertSame('', TonSendBocResult::fromArray([])->hash);
    }
}
