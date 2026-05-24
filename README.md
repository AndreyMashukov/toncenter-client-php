# amashukov/toncenter-client-php

Typed PHP toncenter v2 client for The Open Network — PSR-18 transport, `{ok, result}` envelope unwrapping, retry-aware, typed Value Objects.

[![CI](https://img.shields.io/github/actions/workflow/status/AndreyMashukov/toncenter-client-php/ci.yml?branch=main&label=CI)](https://github.com/AndreyMashukov/toncenter-client-php/actions)
[![PHPStan L9](https://img.shields.io/github/actions/workflow/status/AndreyMashukov/toncenter-client-php/stan.yml?branch=main&label=PHPStan%20L9)](https://github.com/AndreyMashukov/toncenter-client-php/actions)
[![Latest Version](https://img.shields.io/packagist/v/amashukov/toncenter-client-php)](https://packagist.org/packages/amashukov/toncenter-client-php)
[![Downloads](https://img.shields.io/packagist/dt/amashukov/toncenter-client-php)](https://packagist.org/packages/amashukov/toncenter-client-php)
[![PHP](https://img.shields.io/packagist/dependency-v/amashukov/toncenter-client-php/php)](https://packagist.org/packages/amashukov/toncenter-client-php)
[![License](https://img.shields.io/packagist/l/amashukov/toncenter-client-php)](LICENSE)
[![Stars](https://img.shields.io/github/stars/AndreyMashukov/toncenter-client-php?style=social)](https://github.com/AndreyMashukov/toncenter-client-php)

A **typed PHP client for the [toncenter v2 HTTP API](https://toncenter.com/api/v2/)** on [The Open Network (TON)](https://ton.org). It speaks any [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client, unwraps the `{ok, result}` envelope into typed exceptions in one place, and returns Value Objects instead of loose arrays — masterchain info, account state, a run-get-method stack reader, and typed transactions. TON balances exceed `PHP_INT_MAX` past ~9,223 TON, so every nano-TON value comes back as a decimal string, never a lossy float. Includes `ToncenterWalletRpc`, a drop-in `WalletRpcInterface` adapter for [`ton-wallet-php`](https://github.com/AndreyMashukov/ton-wallet-php).

## Features

- **`{ok, result}` envelope unwrapping in one place** — a non-`200` status, `ok: false`, or a missing `result` becomes a `TonRpcException`; business code never touches the envelope.
- **Typed Value Objects** — masterchain info, account state, run-get-method stack reader, and a five-state transaction status (`Pending`, `Success`, `ComputePhaseFailed`, `ActionPhaseFailed`, `Aborted`) derived from the TVM compute / action phases.
- **Decimal-string big numbers** — balances and gas values are returned as decimal strings, safe past `PHP_INT_MAX`.
- **Transport-agnostic** — bring your own PSR-18 client and PSR-17 factories. The retry policy and the `X-Api-Key` rate-limit header are middleware concerns, not baked into the client.
- **542 / 429 retry decider** — toncenter emits transient `542` ("no workers available") and `429`; the recommended retry status set is documented so a retry middleware can target exactly those.
- **Wallet RPC adapter** — `ToncenterWalletRpc` implements `Amashukov\TonWallet\WalletRpcInterface` (`getSeqno` + `sendBoc`) so a [`ton-wallet-php`](https://github.com/AndreyMashukov/ton-wallet-php) wallet broadcasts through toncenter with no glue code.

## Why amashukov/toncenter-client-php

The common alternative is hand-rolled Guzzle calls scattered across a service layer, each re-implementing envelope checks, bigint parsing, and 542/429 retry logic. This package centralizes all of that behind a typed surface with PHPStan level 9, returns Value Objects instead of arrays, and stays transport-agnostic so retry / API-key concerns live in your PSR-18 pipeline.

## Installation

```bash
composer require amashukov/toncenter-client-php
```

## Usage

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

### Reading get-method results

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

### Broadcasting a wallet transfer

```php
use Amashukov\Toncenter\ToncenterWalletRpc;

$rpc = new ToncenterWalletRpc($client);

$seqno = $rpc->getSeqno($walletRawAddress); // 0 if the wallet is not yet deployed
$rpc->sendBoc($signedTransferBocBase64);
```

### API

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

## Requirements

- PHP 8.3+
- `ext-gmp` (bigint decoding of get-method stack values)
- A PSR-18 client + PSR-17 request / stream factories

## Related packages

| Package | Role |
|---------|------|
| [amashukov/ton-wallet-php](https://github.com/AndreyMashukov/ton-wallet-php) | Wallet v4r2 + `WalletRpcInterface` this client adapts (dependency) |
| [amashukov/http-client-php](https://github.com/AndreyMashukov/http-client-php) | PSR-18 cURL client + retry / header-injection middleware |
| [amashukov/ton-cell-php](https://github.com/AndreyMashukov/ton-cell-php) | TLB Cell / Builder / BOC layer |
| [amashukov/ton-php](https://github.com/AndreyMashukov/ton-php) | TON meta-package |
| [amashukov/blockchain-context-bundle](https://github.com/AndreyMashukov/blockchain-context-bundle) | Symfony bundle wiring the whole family |

## Quality

- **PHPStan level 9**, clean.
- **php-cs-fixer** `@PER-CS` ruleset.
- **GitHub Actions CI** on every push.

## License

MIT — see [LICENSE](LICENSE).
