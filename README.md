# toncenter-client-php

A typed PHP client for the [toncenter v2 HTTP API](https://toncenter.com/api/v2/)
on [The Open Network (TON)](https://ton.org). It speaks any
[PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client, unwraps the
`{ok, result}` envelope into typed exceptions, and returns Value Objects
instead of loose arrays.

## Features

- **`{ok, result}` envelope unwrapping in one place** — a non-`200` status,
  `ok: false`, or a missing `result` becomes a `TonRpcException`; business
  code never touches the envelope.
- **Typed Value Objects** — masterchain info, account state, run-get-method
  stack reader, and a five-state transaction status (`Pending`, `Success`,
  `ComputePhaseFailed`, `ActionPhaseFailed`, `Aborted`) derived from the TVM
  compute/action phases.
- **Decimal-string big numbers** — TON balances exceed `PHP_INT_MAX` past
  ~9,223 TON, so balances and gas values are returned as decimal strings.
- **Transport-agnostic** — bring your own PSR-18 client and PSR-17 factories.
  Retry policy and the `X-Api-Key` rate-limit header are middleware concerns,
  not baked into this client.
- **Wallet RPC adapter** — `ToncenterWalletRpc` implements the
  `Amashukov\TonWallet\WalletRpcInterface` (`getSeqno` + `sendBoc`) so a
  [`ton-wallet-php`](https://github.com/AndreyMashukov/ton-wallet-php) wallet
  can broadcast transfers through toncenter with no glue code.

## Requirements

- PHP 8.3+
- `ext-gmp` (bigint decoding of get-method stack values)
- A PSR-18 client + PSR-17 request/stream factories

## Installation

```bash
composer require amashukov/toncenter-client-php
```

## Quick start

Any PSR-18 client and PSR-17 factory pair works. The example below wires
[`amashukov/http-client-php`](https://github.com/AndreyMashukov/http-client-php)
(cURL transport + retry / header-injection middleware) with
[`nyholm/psr7`](https://github.com/Nyholm/psr7) for the PSR-7 messages:

```php
use Amashukov\HttpClient\CurlClient;
use Amashukov\HttpClient\Middleware\HeaderInjectionMiddleware;
use Amashukov\HttpClient\Middleware\RetryMiddleware;
use Amashukov\HttpClient\Pipeline;
use Amashukov\Toncenter\ToncenterClient;
use Nyholm\Psr7\Factory\Psr17Factory;

$psr17 = new Psr17Factory();

$http = new Pipeline(
    new CurlClient($psr17, $psr17, timeoutSeconds: 60),
    [
        // X-Api-Key lifts the rate limit from 1 RPS to 10 RPS (optional).
        new HeaderInjectionMiddleware(['X-Api-Key' => $apiKey]),
        // Toncenter emits transient 542 ("no workers available") + 429; retry those.
        new RetryMiddleware(maxAttempts: 3, retryStatusCodes: [429, 500, 502, 503, 504, 542]),
    ],
);

$client = new ToncenterClient($http, $psr17, $psr17);

$info    = $client->getMasterchainInfo();        // TonMasterchainInfo
$balance = $client->getBalance('EQ...');          // decimal-string nano-TON
$account = $client->getAddressInformation('EQ...');
if ($account->isActive()) {
    // ...
}
```

## Reading get-method results

```php
$result = $client->runMethod('EQ...', 'get_wallet_data');
if ($result->isOk()) {
    $balance    = $result->stack->readBigInt();    // numeric-string
    $ownerSlice = $result->stack->readSliceBoc();   // base64 BOC
}
```

The stack reader is a cursor over a TON `TupleItem` sum-type
(`TonTupleItemInt` / `Cell` / `Slice` / `Builder` / `Null` / `Tuple`) and
decodes both decimal and `0x`-prefixed bigints via GMP.

## Broadcasting a wallet transfer

```php
use Amashukov\Toncenter\ToncenterWalletRpc;

$rpc = new ToncenterWalletRpc($client);

$seqno = $rpc->getSeqno($walletRawAddress); // 0 if the wallet is not yet deployed
$rpc->sendBoc($signedTransferBocBase64);
```

## API

| Method | Returns |
|--------|---------|
| `getMasterchainInfo()` | `TonMasterchainInfo` |
| `getBalance(string $address)` | `string` (decimal nano-TON) |
| `getAddressInformation(string $address)` | `TonAccountInfo` |
| `isContractDeployed(string $address)` | `bool` |
| `getTypedTransactions(string $address, array $opts = [])` | `list<TonTransaction>` |
| `getTypedTransaction(string $address, string $lt, string $hash)` | `TonTransaction` |
| `runMethod(string $address, string $method, array $stack = [])` | `TonRunMethodResult` |
| `sendBoc(string $bocBase64)` | `TonSendBocResult` |

## Testing

```bash
composer install
composer test     # PHPUnit
composer stan     # PHPStan (level 9)
composer cs       # php-cs-fixer (dry-run)
composer rector   # Rector (dry-run)
```

## License

MIT — see [LICENSE](LICENSE).
