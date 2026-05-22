<?php

declare(strict_types=1);

namespace SoureCode\Bundle\TraceableBundle\HttpClient;

use SoureCode\Component\Traceable\TraceContextHolder;
use Symfony\Component\HttpClient\DecoratorTrait;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Stamps every outbound HTTP request with the active trace id under the
 * configured header (defaults to "X-Request-Id"). Wire as a decorator
 * around your `http_client` service.
 */
final class TracingHttpClient implements HttpClientInterface
{
    use DecoratorTrait;

    public function __construct(
        HttpClientInterface $client,
        private readonly TraceContextHolder $holder,
        private readonly string $header = 'X-Request-Id',
    ) {
        $this->client = $client;
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $context = $this->holder->getCurrent();

        if ($context !== null) {
            $options['headers'] ??= [];

            if (!isset($options['headers'][$this->header])) {
                $options['headers'][$this->header] = (string) $context->getId();
            }
        }

        return $this->client->request($method, $url, $options);
    }
}
