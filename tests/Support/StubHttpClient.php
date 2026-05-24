<?php

declare(strict_types=1);

namespace Amashukov\Toncenter\Tests\Support;

use LogicException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class StubHttpClient implements ClientInterface
{
    private ?RequestInterface $lastRequest = null;

    public function __construct(
        private readonly ?ResponseInterface $response = null,
        private readonly ?ClientExceptionInterface $throw = null,
    ) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        if ($this->throw instanceof ClientExceptionInterface) {
            throw $this->throw;
        }
        if (!$this->response instanceof ResponseInterface) {
            throw new LogicException('StubHttpClient: no response configured');
        }

        return $this->response;
    }

    public function lastRequest(): ?RequestInterface
    {
        return $this->lastRequest;
    }
}
