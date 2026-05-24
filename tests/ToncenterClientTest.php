<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests;

use Amashukov\Toncenter\Tests\Support\StubClientException;
use Amashukov\Toncenter\Tests\Support\StubHttpClient;
use Amashukov\Toncenter\ToncenterClient;
use Amashukov\Toncenter\TonRpcException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class ToncenterClientTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function testGetMasterchainInfoUnwrapsResultAndIssuesGet(): void
    {
        $stub   = new StubHttpClient($this->json(200, '{"ok":true,"result":{"last":{"seqno":99,"workchain":-1},"init":{"seqno":0},"state_root_hash":"S=="}}'));
        $client = new ToncenterClient($stub, $this->factory, $this->factory);

        $info = $client->getMasterchainInfo();

        self::assertSame(99, $info->last->seqno);
        self::assertSame('S==', $info->stateRootHash);

        $request = $this->lastRequest($stub);
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://toncenter.com/api/v2/getMasterchainInfo', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    public function testGetBalanceReturnsDecimalStringAndUrlEncodesAddress(): void
    {
        $stub   = new StubHttpClient($this->json(200, '{"ok":true,"result":"12500000000"}'));
        $client = new ToncenterClient($stub, $this->factory, $this->factory);

        self::assertSame('12500000000', $client->getBalance('EQ+slash/plus'));

        $request = $this->lastRequest($stub);
        self::assertSame('https://toncenter.com/api/v2/getAddressBalance?address=EQ%2Bslash%2Fplus', (string) $request->getUri());
    }

    public function testGetAddressInformationAndDeploymentPredicate(): void
    {
        $stub   = new StubHttpClient($this->json(200, '{"ok":true,"result":{"state":"active","balance":"42"}}'));
        $client = new ToncenterClient($stub, $this->factory, $this->factory);

        self::assertTrue($client->isContractDeployed('EQAddr'));
    }

    public function testRunMethodPostsJsonBodyAndReadsStack(): void
    {
        $stub   = new StubHttpClient($this->json(200, '{"ok":true,"result":{"ok":true,"exit_code":0,"gas_used":100,"stack":[["num","0x2a"]]}}'));
        $client = new ToncenterClient($stub, $this->factory, $this->factory);

        $result = $client->runMethod('EQAddr', 'seqno', [['num', '1']]);

        self::assertSame(42, $result->stack->readNumber());

        $request = $this->lastRequest($stub);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://toncenter.com/api/v2/runGetMethod', (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));

        $decoded = json_decode((string) $request->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['address' => 'EQAddr', 'method' => 'seqno', 'stack' => [['num', '1']]], $decoded);
    }

    public function testSendBocPostsBocAndReturnsHash(): void
    {
        $stub   = new StubHttpClient($this->json(200, '{"ok":true,"result":{"hash":"BROADCAST=="}}'));
        $client = new ToncenterClient($stub, $this->factory, $this->factory);

        self::assertSame('BROADCAST==', $client->sendBoc('te6ccBASE64')->hash);

        $decoded = json_decode((string) $this->lastRequest($stub)->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['boc' => 'te6ccBASE64'], $decoded);
    }

    public function testGetTypedTransactionsWrapsEachRow(): void
    {
        $stub   = new StubHttpClient($this->json(200, '{"ok":true,"result":[{"transaction_id":{"hash":"h1","lt":"10"},"description":{"compute_ph":{"type":"vm","success":true,"exit_code":0},"aborted":false}}]}'));
        $client = new ToncenterClient($stub, $this->factory, $this->factory);

        $txs = $client->getTypedTransactions('EQAddr', ['limit' => 1, 'archival' => true]);

        self::assertCount(1, $txs);
        self::assertTrue($txs[0]->isStatusSuccess());

        $uri = (string) $this->lastRequest($stub)->getUri();
        self::assertStringContainsString('archival=true', $uri);
        self::assertStringContainsString('limit=1', $uri);
    }

    public function testGetTypedTransactionReturnsPendingOnLtMismatch(): void
    {
        $stub   = new StubHttpClient($this->json(200, '{"ok":true,"result":[{"transaction_id":{"hash":"h1","lt":"999"},"description":{"aborted":false}}]}'));
        $client = new ToncenterClient($stub, $this->factory, $this->factory);

        $tx = $client->getTypedTransaction('EQAddr', '10', 'h1');

        self::assertTrue($tx->isStatusPending());
    }

    public function testGetTypedTransactionMatchesOnLt(): void
    {
        $stub   = new StubHttpClient($this->json(200, '{"ok":true,"result":[{"transaction_id":{"hash":"h1","lt":"10"},"description":{"compute_ph":{"type":"vm","success":true,"exit_code":0},"aborted":false}}]}'));
        $client = new ToncenterClient($stub, $this->factory, $this->factory);

        $tx = $client->getTypedTransaction('EQAddr', '10', 'h1');

        self::assertTrue($tx->isStatusSuccess());
        self::assertSame('h1', $tx->hash);
    }

    public function testNon200ThrowsTonRpcException(): void
    {
        $stub   = new StubHttpClient($this->json(500, 'upstream down'));
        $client = new ToncenterClient($stub, $this->factory, $this->factory);

        $this->expectException(TonRpcException::class);
        $this->expectExceptionMessage('returned HTTP 500');
        $client->getMasterchainInfo();
    }

    public function testOkFalseEnvelopeThrowsWithCode(): void
    {
        $stub   = new StubHttpClient($this->json(200, '{"ok":false,"error":"rate limited","code":429}'));
        $client = new ToncenterClient($stub, $this->factory, $this->factory);

        $this->expectException(TonRpcException::class);
        $this->expectExceptionCode(429);
        $client->getMasterchainInfo();
    }

    public function testMissingResultThrows(): void
    {
        $stub   = new StubHttpClient($this->json(200, '{"ok":true}'));
        $client = new ToncenterClient($stub, $this->factory, $this->factory);

        $this->expectException(TonRpcException::class);
        $this->expectExceptionMessage('missing "result"');
        $client->getMasterchainInfo();
    }

    public function testInvalidJsonThrows(): void
    {
        $stub   = new StubHttpClient($this->json(200, 'not json{'));
        $client = new ToncenterClient($stub, $this->factory, $this->factory);

        $this->expectException(TonRpcException::class);
        $this->expectExceptionMessage('invalid JSON');
        $client->getMasterchainInfo();
    }

    public function testTransportExceptionIsWrapped(): void
    {
        $stub   = new StubHttpClient(null, new StubClientException('connection refused'));
        $client = new ToncenterClient($stub, $this->factory, $this->factory);

        $this->expectException(TonRpcException::class);
        $this->expectExceptionMessage('connection refused');
        $client->getMasterchainInfo();
    }

    public function testCustomBaseUrlIsHonoured(): void
    {
        $stub   = new StubHttpClient($this->json(200, '{"ok":true,"result":"0"}'));
        $client = new ToncenterClient($stub, $this->factory, $this->factory, 'https://my-proxy.local/ton/v2');

        $client->getBalance('EQAddr');

        self::assertStringStartsWith('https://my-proxy.local/ton/v2/getAddressBalance', (string) $this->lastRequest($stub)->getUri());
    }

    private function json(int $status, string $body): ResponseInterface
    {
        return $this->factory->createResponse($status)->withBody($this->factory->createStream($body));
    }

    private function lastRequest(StubHttpClient $stub): RequestInterface
    {
        $request = $stub->lastRequest();
        if (!$request instanceof RequestInterface) {
            self::fail('no request was issued');
        }

        return $request;
    }
}
