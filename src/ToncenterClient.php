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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class ToncenterClient implements ToncenterClientInterface
{
    public const string DEFAULT_BASE_URL = 'https://toncenter.com/api/v2';

    private const int NOT_FOUND = 404;

    public function __construct(
        private ClientInterface $http,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private string $baseUrl = self::DEFAULT_BASE_URL,
        private LoggerInterface $logger = new NullLogger(),
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
            $typed[] = $this->transactionWithData($row, $address, '/getTransactions');
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

        return $this->transactionWithData($first, $address, '/getTransactions');
    }

    public function tryLocateResultTx(string $source, string $destination, string $createdLt): ?TonTransaction
    {
        try {
            $row = $this->getJson('/tryLocateResultTx?' . http_build_query([
                'source'      => $source,
                'destination' => $destination,
                'created_lt'  => $createdLt,
            ]), expectedCodes: [self::NOT_FOUND]);
        } catch (TonRpcException $exception) {
            if (self::NOT_FOUND === $exception->getCode()) {
                $this->logger->debug('Toncenter tryLocateResultTx: no result transaction for this message, answering null', [
                    'source'      => $source,
                    'destination' => $destination,
                    'created_lt'  => $createdLt,
                ]);

                return null;
            }

            throw $exception;
        }

        if (!is_array($row)) {
            throw new TonRpcException('Toncenter GET /tryLocateResultTx: result is not a transaction object');
        }

        return $this->transactionWithData($this->stringKeyed($row), $destination, '/tryLocateResultTx');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function transactionWithData(array $row, string $address, string $path): TonTransaction
    {
        $data = $row['data'] ?? null;
        if (!is_string($data) || '' === $data) {
            throw new TonRpcException(sprintf('Toncenter GET %s: a transaction row carries no "data", so its phases cannot be read', $path));
        }

        return TonTransaction::fromToncenter($row, $address);
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
                $rows[] = $this->stringKeyed($row);
            }
        }

        return $rows;
    }

    /**
     * @param array<array-key, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function stringKeyed(array $row): array
    {
        $keyed = [];
        foreach ($row as $key => $value) {
            $keyed[(string) $key] = $value;
        }

        return $keyed;
    }

    /**
     * @param list<int> $expectedCodes
     */
    private function getJson(string $pathQs, array $expectedCodes = []): mixed
    {
        return $this->dispatch('GET', $pathQs, null, $expectedCodes);
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
     * @param list<int>                 $expectedCodes
     */
    private function dispatch(string $method, string $path, ?array $body, array $expectedCodes = []): mixed
    {
        $request = $this->requestFactory
            ->createRequest($method, $this->baseUrl . $path)
            ->withHeader('Accept', 'application/json');

        if (null !== $body) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($this->encode($body, $method, $path)));
        }

        $this->logger->debug('Toncenter request', ['method' => $method, 'path' => $path]);

        try {
            $response = $this->http->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw $this->failed(new TonRpcException(sprintf('Toncenter %s %s: %s', $method, $path, $exception->getMessage()), 0, $exception), $method, $path, $expectedCodes);
        }

        $status = $response->getStatusCode();
        $this->logger->debug('Toncenter response', ['method' => $method, 'path' => $path, 'status' => $status]);

        if (200 !== $status) {
            throw $this->failed($this->statusError($method, $path, $status, (string) $response->getBody()), $method, $path, $expectedCodes);
        }

        try {
            $json = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw $this->failed(new TonRpcException(sprintf('Toncenter %s %s: invalid JSON', $method, $path), 0, $exception), $method, $path, $expectedCodes);
        }

        if (!is_array($json)) {
            throw $this->failed(new TonRpcException(sprintf('Toncenter %s %s: response is not a JSON object', $method, $path)), $method, $path, $expectedCodes);
        }

        if (true !== ($json['ok'] ?? null)) {
            $err  = Wire::str($json['error'] ?? null, 'unknown');
            $code = Wire::int($json['code'] ?? null);

            throw $this->failed(new TonRpcException(sprintf('Toncenter %s %s: [%d] %s', $method, $path, $code, $err), $code), $method, $path, $expectedCodes);
        }

        if (!\array_key_exists('result', $json)) {
            throw $this->failed(new TonRpcException(sprintf('Toncenter %s %s: missing "result"', $method, $path)), $method, $path, $expectedCodes);
        }

        return $json['result'];
    }

    /**
     * @param list<int> $expectedCodes
     */
    private function failed(TonRpcException $exception, string $method, string $path, array $expectedCodes): TonRpcException
    {
        $context = ['exception' => $exception, 'method' => $method, 'path' => $path, 'code' => $exception->getCode()];

        if (\in_array($exception->getCode(), $expectedCodes, true)) {
            $this->logger->debug('Toncenter request answered with an expected error code', $context);

            return $exception;
        }

        $this->logger->error('Toncenter request failed', $context);

        return $exception;
    }

    private function statusError(string $method, string $path, int $status, string $body): TonRpcException
    {
        $summary = sprintf('Toncenter %s %s returned HTTP %d', $method, $path, $status);

        try {
            $json = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new TonRpcException($summary);
        }

        if (!is_array($json) || false !== ($json['ok'] ?? null)) {
            return new TonRpcException($summary);
        }

        $code = Wire::int($json['code'] ?? null);

        return new TonRpcException(sprintf('%s: [%d] %s', $summary, $code, Wire::str($json['error'] ?? null, 'unknown')), $code);
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
