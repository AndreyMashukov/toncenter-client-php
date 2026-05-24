<?php

declare(strict_types=1);

namespace Amashukov\Toncenter;

use Amashukov\TonWallet\WalletRpcInterface;

final readonly class ToncenterWalletRpc implements WalletRpcInterface
{
    public function __construct(
        private ToncenterClientInterface $client,
    ) {}

    public function getSeqno(string $rawAddress): int
    {
        $result = $this->client->runMethod($rawAddress, 'seqno');
        if (!$result->isOk() || 0 === $result->stack->remaining()) {
            return 0;
        }

        return $result->stack->readNumber();
    }

    public function sendBoc(string $base64Boc): void
    {
        $this->client->sendBoc($base64Boc);
    }
}
