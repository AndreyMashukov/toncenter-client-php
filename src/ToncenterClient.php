<?php

declare(strict_types=1);

namespace Amashukov\Toncenter;

use Amashukov\Toncenter\Vo\TonAccountInfo;
use Amashukov\Toncenter\Vo\TonMasterchainInfo;
use Amashukov\Toncenter\Vo\TonRunMethodResult;
use Amashukov\Toncenter\Vo\TonSendBocResult;
use Amashukov\Toncenter\Vo\TonTransaction;
use Amashukov\Toncenter\Vo\Wire;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class ToncenterClient implements ToncenterClientInterface
{
    public const string DEFAULT_BASE_URL = 'https://toncenter.com/api/v2';

    public function __construct(
        private ClientInterface $http,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private string $baseUrl = self::DEFAULT_BASE_URL,
    ) {}

    public function getMasterchainInfo(): TonMasterchainInfo
    {
        $envelope = $this->getJson('/getMasterchainInfo');

        return TonMasterchainInfo::fromToncenter(is_array($envelope) ? $envelope : []);
    }

    public function getBalance(string $address): string
    {
        $raw = $this->getJson('/getAddressBalance?address=' . rawurlencode($address));

        return is_scalar($raw) ? (string) $raw : '0';
    }

    public function getAddressInformation(string $address): TonAccountInfo
    {
        $row = $this->getJson('/getAddressInformation?address=' . rawurlencode($address));

        return TonAccountInfo::fromArray($address, is_array($row) ? $row : []);
    }

    public function isContractDeployed(string $address): bool
    {
        return $this->getAddressInformation($address)->isActive();
    }

    public function getTypedTransactions(string $address, array $opts = []): array
    {
        $typed = [];
        foreach ($this->fetchTransactions($address, $opts) as $row) {
            $typed[] = TonTransaction::fromToncenter($row, $address);
        }

        return $typed;
    }

    public function getTypedTransaction(string $address, string $lt, string $hash): TonTransaction
    {
        $rows = $this->fetchTransactions($address, ['lt' => $lt, 'hash' => $hash, 'limit' => 1]);
        if ([] === $rows) {
            return TonTransaction::fromToncenter(null, $address);
        }
        $first = $rows[0];
        $txId  = $first['transaction_id'] ?? null;
        if (!is_array($txId) || Wire::str($txId['lt'] ?? null) !== $lt) {
            return TonTransaction::fromToncenter(null, $address);
        }

        return TonTransaction::fromToncenter($first, $address);
    }

    public function runMethod(string $address, string $method, array $stack = []): TonRunMethodResult
    {
        $raw = $this->postJson('/runGetMethod', [
            'address' => $address,
            'method'  => $method,
            'stack'   => $stack,
        ]);

        return TonRunMethodResult::fromToncenter(is_array($raw) ? $raw : []);
    }

    public function sendBoc(string $bocBase64): TonSendBocResult
    {
        $raw = $this->postJson('/sendBoc', ['boc' => $bocBase64]);

        return TonSendBocResult::fromArray(is_array($raw) ? $raw : []);
    }

    /**
     * @param array{limit?: int, lt?: string, hash?: string, to_lt?: string, archival?: bool} $opts
     *
     * @return list<array<string, mixed>>
     */
    private function fetchTransactions(string $address, array $opts = []): array
    {
        $qs = ['address' => $address];
        foreach (['limit', 'lt', 'hash', 'to_lt'] as $k) {
            if (isset($opts[$k])) {
                $qs[$k] = (string) $opts[$k];
            }
        }
        if (!empty($opts['archival'])) {
            $qs['archival'] = 'true';
        }

        $raw = $this->getJson('/getTransactions?' . http_build_query($qs));

        $rows = [];
        foreach (is_array($raw) ? $raw : [] as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function getJson(string $pathQs): mixed
    {
        return $this->dispatch('GET', $pathQs, null);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function postJson(string $path, array $body): mixed
    {
        return $this->dispatch('POST', $path, $body);
    }

    /**
     * @param null|array<string, mixed> $body
     */
    private function dispatch(string $method, string $path, ?array $body): mixed
    {
        $request = $this->requestFactory
            ->createRequest($method, $this->baseUrl . $path)
            ->withHeader('Accept', 'application/json');

        if (null !== $body) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($this->encode($body, $method, $path)));
        }

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new TonRpcException(sprintf('Toncenter %s %s: %s', $method, $path, $exception->getMessage()), 0, $exception);
        }

        $status = $response->getStatusCode();
        if (200 !== $status) {
            throw new TonRpcException(sprintf('Toncenter %s %s returned HTTP %d', $method, $path, $status));
        }

        try {
            $json = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new TonRpcException(sprintf('Toncenter %s %s: invalid JSON', $method, $path), 0, $exception);
        }

        if (!is_array($json)) {
            throw new TonRpcException(sprintf('Toncenter %s %s: response is not a JSON object', $method, $path));
        }

        if (true !== ($json['ok'] ?? null)) {
            $err  = Wire::str($json['error'] ?? null, 'unknown');
            $code = Wire::int($json['code'] ?? null);

            throw new TonRpcException(sprintf('Toncenter %s %s: [%d] %s', $method, $path, $code, $err), $code);
        }

        if (!\array_key_exists('result', $json)) {
            throw new TonRpcException(sprintf('Toncenter %s %s: missing "result"', $method, $path));
        }

        return $json['result'];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function encode(array $body, string $method, string $path): string
    {
        try {
            return json_encode($body, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new TonRpcException(sprintf('Toncenter %s %s: request body not encodable', $method, $path), 0, $exception);
        }
    }
}
