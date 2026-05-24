<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests;

use Psr\Http\Message\RequestInterface;
use Amashukov\Toncenter\Tests\Support\StubHttpClient;
use Amashukov\Toncenter\ToncenterClient;
use Amashukov\Toncenter\ToncenterWalletRpc;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class ToncenterWalletRpcTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function testGetSeqnoReadsRunMethodStack(): void
    {
        $adapter = $this->adapter($this->json('{"ok":true,"result":{"ok":true,"exit_code":0,"gas_used":50,"stack":[["num","0x05"]]}}'));

        self::assertSame(5, $adapter->getSeqno('0:abc'));
    }

    public function testGetSeqnoReturnsZeroWhenContractNotDeployed(): void
    {
        $adapter = $this->adapter($this->json('{"ok":true,"result":{"ok":false,"exit_code":-13,"gas_used":0,"stack":[]}}'));

        self::assertSame(0, $adapter->getSeqno('0:abc'));
    }

    public function testGetSeqnoReturnsZeroWhenStackEmpty(): void
    {
        $adapter = $this->adapter($this->json('{"ok":true,"result":{"ok":true,"exit_code":0,"gas_used":0,"stack":[]}}'));

        self::assertSame(0, $adapter->getSeqno('0:abc'));
    }

    public function testSendBocDelegatesToClient(): void
    {
        $stub    = new StubHttpClient($this->json('{"ok":true,"result":{"hash":"BROADCAST=="}}'));
        $adapter = new ToncenterWalletRpc(new ToncenterClient($stub, $this->factory, $this->factory));

        $adapter->sendBoc('te6ccBASE64');

        $request = $stub->lastRequest();
        if (!$request instanceof RequestInterface) {
            self::fail('no request issued');
        }
        self::assertSame('https://toncenter.com/api/v2/sendBoc', (string) $request->getUri());
        $decoded = json_decode((string) $request->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['boc' => 'te6ccBASE64'], $decoded);
    }

    private function adapter(ResponseInterface $response): ToncenterWalletRpc
    {
        return new ToncenterWalletRpc(new ToncenterClient(new StubHttpClient($response), $this->factory, $this->factory));
    }

    private function json(string $body): ResponseInterface
    {
        return $this->factory->createResponse(200)->withBody($this->factory->createStream($body));
    }
}
