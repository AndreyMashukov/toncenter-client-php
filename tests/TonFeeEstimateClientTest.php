<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests;

use Amashukov\Toncenter\Tests\Support\StubHttpClient;
use Amashukov\Toncenter\ToncenterClient;
use Amashukov\Toncenter\TonRpcException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use LogicException;

final class TonFeeEstimateClientTest extends TestCase
{
    private const string RESPONSE = '{"ok":true,"result":{"source_fees":{"in_fwd_fee":0,"storage_fee":2,"gas_fee":9223372036854775808,"fwd_fee":7},"destination_fees":[]}}';

    public function testEstimatesWithoutBroadcastOrDeploymentFields(): void
    {
        $factory = new Psr17Factory();
        $http = new StubHttpClient($factory->createResponse(200)->withBody($factory->createStream(self::RESPONSE)));
        $client = new ToncenterClient($http, $factory, $factory);
        $estimate = $client->estimateFee('0:' . str_repeat('0', 64), 'body-boc');
        self::assertSame('9223372036854775808', $estimate->sourceFees->gasFee);
        self::assertSame([], $estimate->destinationFees);
        $request = $this->request($http);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://toncenter.com/api/v2/estimateFee', (string) $request->getUri());
        self::assertSame(['address' => '0:' . str_repeat('0', 64), 'body' => 'body-boc', 'ignore_chksig' => true], json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testPassesInitDataAndSignaturePolicy(): void
    {
        $factory = new Psr17Factory();
        $http = new StubHttpClient($factory->createResponse(200)->withBody($factory->createStream(self::RESPONSE)));
        $client = new ToncenterClient($http, $factory, $factory);
        $client->estimateFee('0:' . str_repeat('1', 64), 'signed-body', 'code-boc', 'data-boc', false);
        self::assertSame(['address' => '0:' . str_repeat('1', 64), 'body' => 'signed-body', 'ignore_chksig' => false, 'init_code' => 'code-boc', 'init_data' => 'data-boc'], json_decode((string) $this->request($http)->getBody(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testRejectsProviderFailure(): void
    {
        $factory = new Psr17Factory();
        $http = new StubHttpClient($factory->createResponse(200)->withBody($factory->createStream('{"ok":false,"error":"cannot estimate","code":500}')));
        $client = new ToncenterClient($http, $factory, $factory);
        $this->expectException(TonRpcException::class);
        $this->expectExceptionMessage('cannot estimate');
        $client->estimateFee('0:' . str_repeat('0', 64), 'body-boc');
    }

    public function testRejectsMissingGasInsteadOfReturningFreeEstimate(): void
    {
        $factory = new Psr17Factory();
        $http = new StubHttpClient($factory->createResponse(200)->withBody($factory->createStream('{"ok":true,"result":{"source_fees":{},"destination_fees":[]}}')));
        $client = new ToncenterClient($http, $factory, $factory);
        $this->expectException(TonRpcException::class);
        $client->estimateFee('0:' . str_repeat('0', 64), 'body-boc');
    }

    private function request(StubHttpClient $http): RequestInterface
    {
        return $http->lastRequest() ?? throw new LogicException('Expected one estimate request.');
    }
}
