# toncenter-client-php

Typed PHP client for the toncenter v2 HTTP API (<https://toncenter.com/api/v2/>).

Features:

- `{ok: true, result: …}` envelope unwrapping; `{ok: false, error, code}` responses are surfaced as typed exceptions.
- `X-Api-Key` header injection.
- Retry middleware on 542 / 429 responses with exponential backoff.
- Typed return value objects for `getMasterchainInfo`, `getAddressInformation`, `runMethod`, `sendBoc`, `getTransactions`.

## Status

Pre-1.0. Public API may change before the 1.0 tag.

## Requirements

- PHP 8.3+
- `ext-curl`
- `ext-gmp`

## Dependencies

- [`amashukov/http-client-php`](https://github.com/AndreyMashukov/http-client-php)
- [`amashukov/ton-cell-php`](https://github.com/AndreyMashukov/ton-cell-php)

## License

MIT License.
