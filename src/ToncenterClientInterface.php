<?php

declare(strict_types=1);

namespace Amashukov\Toncenter;

use Amashukov\Toncenter\Vo\TonAccountInfo;
use Amashukov\Toncenter\Vo\TonMasterchainInfo;
use Amashukov\Toncenter\Vo\TonRunMethodResult;
use Amashukov\Toncenter\Vo\TonSendBocResult;
use Amashukov\Toncenter\Vo\TonTransaction;

interface ToncenterClientInterface
{
    /**
     * Latest masterchain block envelope. `.last.seqno` is the canonical
     * source-of-truth for finality-depth counting.
     */
    public function getMasterchainInfo(): TonMasterchainInfo;

    /**
     * Account balance in nanotons as a decimal string. String — not int —
     * because TON supply (5e18+ nanotons) approaches PHP_INT_MAX (9.2e18).
     */
    public function getBalance(string $address): string;

    /**
     * Typed account info: state (Active/Uninitialized/Frozen), decimal-string
     * balance, optional code/data BOC, last transaction id.
     */
    public function getAddressInformation(string $address): TonAccountInfo;

    /**
     * True when the account state is `active` (code deployed).
     */
    public function isContractDeployed(string $address): bool;

    /**
     * Typed transaction list for $address. Each toncenter row collapses
     * envelope-level `aborted` + per-phase `success` flags into one of five
     * `TonTransactionStatus` cases.
     *
     * @param array{limit?: int, lt?: string, hash?: string, to_lt?: string, archival?: bool} $opts
     *
     * @return list<TonTransaction>
     */
    public function getTypedTransactions(string $address, array $opts = []): array;

    /**
     * Typed transaction for (address, lt, hash). Indexer lag → `Pending`.
     */
    public function getTypedTransaction(string $address, string $lt, string $hash): TonTransaction;

    /**
     * Runs a smart-contract get-method and wraps the response in a typed
     * `TonRunMethodResult` (ok + exit_code + gas_used + `TonTupleReader` stack).
     *
     * @param list<array{0: string, 1: mixed}> $stack each entry is [type, value], e.g. [['num', '100'], ['cell', $bocBase64]]
     */
    public function runMethod(string $address, string $method, array $stack = []): TonRunMethodResult;

    /**
     * Broadcasts a base64-encoded BOC (external-in message). Returns the
     * typed envelope carrying the broadcast hash (when present).
     */
    public function sendBoc(string $bocBase64): TonSendBocResult;
}
