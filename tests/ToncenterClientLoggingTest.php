<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests;

use Amashukov\Toncenter\Tests\Support\RecordingLogger;
use Amashukov\Toncenter\Tests\Support\StubClientException;
use Amashukov\Toncenter\Tests\Support\StubHttpClient;
use Amashukov\Toncenter\ToncenterClient;
use Amashukov\Toncenter\TonRpcException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;

final class ToncenterClientLoggingTest extends TestCase
{
    private Psr17Factory $factory;

    private RecordingLogger $logger;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->logger  = new RecordingLogger();
    }

    public function testASuccessfulCallLogsTheRequestAndTheResponseAtDebug(): void
    {
        $this->client(new StubHttpClient($this->factory->createResponse(200)->withBody($this->factory->createStream('{"ok":true,"result":"42"}'))))->getBalance('EQAddr');

        self::assertSame(['debug Toncenter request', 'debug Toncenter response'], $this->logger->lines());
        self::assertSame(['method' => 'GET', 'path' => '/getAddressBalance?address=EQAddr', 'status' => 200], $this->logger->records()[1]['context']);
    }

    public function testAnErrorAnswerIsLoggedAsAnErrorCarryingTheException(): void
    {
        $client = $this->client(new StubHttpClient($this->factory->createResponse(429)->withBody($this->factory->createStream('{"ok":false,"error":"Ratelimit exceed","code":429}'))));

        try {
            $client->getBalance('EQAddr');
            self::fail('A 429 answer must throw');
        } catch (TonRpcException $exception) {
            self::assertSame(['debug Toncenter request', 'debug Toncenter response', 'error Toncenter request failed'], $this->logger->lines());
            self::assertSame($exception, $this->logger->records()[2]['context']['exception']);
            self::assertSame(429, $this->logger->records()[2]['context']['code']);
        }
    }

    public function testATransportFailureIsLoggedAsAnErrorCarryingTheException(): void
    {
        $client = $this->client(new StubHttpClient(null, new StubClientException('connection refused')));

        try {
            $client->getMasterchainInfo();
            self::fail('A transport failure must throw');
        } catch (TonRpcException $exception) {
            self::assertSame(['debug Toncenter request', 'error Toncenter request failed'], $this->logger->lines());
            self::assertSame($exception, $this->logger->records()[1]['context']['exception']);
        }
    }

    public function testAResultTransactionToncenterCannotFindIsNotAnError(): void
    {
        $client = $this->client(new StubHttpClient($this->factory->createResponse(404)->withBody($this->factory->createStream('{"ok":false,"error":"transaction was not found","code":404}'))));

        self::assertNull($client->tryLocateResultTx('EQSource', 'EQDestination', '1000000'));
        self::assertSame([
            'debug Toncenter request',
            'debug Toncenter response',
            'debug Toncenter request answered with an expected error code',
            'debug Toncenter tryLocateResultTx: no result transaction for this message, answering null',
        ], $this->logger->lines());
    }

    private function client(ClientInterface $http): ToncenterClient
    {
        return new ToncenterClient($http, $this->factory, $this->factory, ToncenterClient::DEFAULT_BASE_URL, $this->logger);
    }
}
